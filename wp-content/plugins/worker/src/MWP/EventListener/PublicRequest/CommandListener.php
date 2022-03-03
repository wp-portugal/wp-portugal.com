<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_PublicRequest_CommandListener implements Symfony_EventDispatcher_EventSubscriberInterface
{
    private $context;
    private $signer;
    private $configuration;
    private $nonceManager;

    public function __construct(MWP_WordPress_Context $context, MWP_Signer_Interface $signer, MWP_Worker_Configuration $configuration, MWP_Security_NonceManager $nonceManager)
    {
        $this->context       = $context;
        $this->signer        = $signer;
        $this->configuration = $configuration;
        $this->nonceManager  = $nonceManager;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::PUBLIC_REQUEST => 'onPublicRequest',
        );
    }

    private function handleImageCheck($queryValue)
    {
        $parts = explode('.', $queryValue);
        if (count($parts) !== 3) {
            return;
        }
        list($keyName, $expiresAt, $signature64) = $parts;
        if ((int)$expiresAt < time()) {
            return;
        }
        $publicKey = $this->findActivePublicKey($keyName);
        if (empty($publicKey)) {
            $this->context->wpDie('Public key could not be fetched', 'Image error');
            exit;
        }
        $signature = self::base64RawUrlDecode($signature64);
        if (!$this->signer->verify("$keyName.$expiresAt", $signature, $publicKey)) {
            return;
        }
        if (headers_sent($file, $line)) {
            $this->context->wpDie(sprintf('Headers already sent in %s:%d', $file, $line), 'Image error');
            exit;
        }
        header('Content-Type: image/png');
        // Prints a 11x7 white PNG image.
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAsAAAAHCAMAAADpsEdvAAAAA1BMVEX///+nxBvIAAAAC0lEQVR4AWOgAQAAAFQAAbJsAWkAAAAASUVORK5CYII=');
        exit;
    }

    private function wpDie($commandUrl, $error, $title)
    {
        $content = htmlspecialchars($error);

        $query = parse_url($commandUrl, PHP_URL_QUERY);
        parse_str((string)$query, $params);
        if (isset($params['state'])) {
            // Append 'error' query parameter to the URL.
            $errorUrl = $params['state'];
            if (strpos($errorUrl, '?') === false) {
                $errorUrl .= '?';
            } else {
                $errorUrl .= '&';
            }
            $errorUrl .= 'error='.urlencode($error);
            $content  .= sprintf('<img src="%s" height="1" width="1"/>', htmlspecialchars($errorUrl));
        }

        $this->context->wpDie($content, $title);
        exit;
    }

    public function onPublicRequest(MWP_Event_PublicRequest $event)
    {
        $query = $event->getRequest()->query;
        if (!empty($query['mwpi'])) {
            $this->handleImageCheck($query['mwpi']);
            return;
        }
        if (empty($query['mwpc'])) {
            return;
        }
        $parts = explode('.', $query['mwpc']);
        if (count($parts) !== 5) {
            return;
        }
        list($keyName, $payloadBase64, $nonce, $pairBase64, $signatureBase64) = $parts;
        $commandUrl = self::base64RawUrlDecode($payloadBase64);
        $parts      = explode('#', $commandUrl, 2);
        $commandUrl = $parts[0];
        $signature  = self::base64RawUrlDecode($signatureBase64);
        $publicKey  = $this->findActivePublicKey($keyName);
        if (empty($publicKey)) {
            $this->wpDie($commandUrl, 'Public key could not be fetched', 'Command error');
            exit;
        }
        if (!$this->signer->verify("$keyName.$payloadBase64.$nonce.$pairBase64", $signature, $publicKey)) {
            $this->wpDie($commandUrl, 'Invalid command signature', 'Command error');
            exit;
        }
        $pair       = self::base64RawUrlDecode($pairBase64);
        $requestUri = $_SERVER['REQUEST_URI'];
        if (!$this->context->isGranted('activate_plugins')) {
            $user = $this->context->getCurrentUser();
            if (!empty($user->ID)) {
                $this->wpDie($commandUrl, 'You need the permission to activate plugins to access this page.', 'Command error');
                exit;
            }
            /** @handled function */
            header('Location: '.site_url('wp-login.php?redirect_to='.urlencode($requestUri)));
            exit;
        }
        $nonceAction = 'sync-'.$commandUrl;
        if (@$_SERVER['REQUEST_METHOD'] !== 'POST') {
            $html = sprintf('<form action="%s" method="post">
Establish connection with <strong>%s</strong>?
<br/>
<br/>
<input type="hidden" name="wp_nonce" value="%s"/>

<a href="#" onclick="window.close(); return false;" style="float: left; line-height: 30px; margin-right: 30px;">Cancel</a>
<button type="submit" class="button button-primary button-large" onclick="if (this.classList.contains(\'clicked\')) return false; this.classList.add(\'clicked\'); this.innerHTML=\'Loading...\'; this.style.opacity=\'0.5\';">Confirm</button>
</form>
', htmlspecialchars($requestUri), htmlspecialchars($pair), wp_create_nonce($nonceAction));
            $this->context->wpDie($html, sprintf('Synchronize with %s', htmlspecialchars($pair)));
            exit;
        }
        $this->context->requirePluggable();
        /** @handled function */
        if (!wp_verify_nonce(@$_POST['wp_nonce'], $nonceAction)) {
            $this->wpDie($commandUrl, 'Invalid WordPress nonce, please retry the process', 'Command error');
            exit;
        }
        try {
            $this->nonceManager->useNonce($nonce);
        } catch (MWP_Security_Exception_NonceAlreadyUsed $e) {
            $this->context->wpDie($commandUrl, 'Command already run', 'Command error');
            exit;
        } catch (Exception $e) {
            $this->wpDie($commandUrl, sprintf('Command nonce error: %s', $e->getMessage()), 'Command error');
            exit;
        }
        $commandParams = array();
        if (isset($parts[1])) {
            parse_str($parts[1], $commandParams);
        }
        try {
            $command = self::httpGetContents($commandUrl);
        } catch (Exception $e) {
            $this->wpDie($commandUrl, sprintf('Could not fetch command from %s: %s', $commandUrl, $e->getMessage()), 'Command error');
            exit;
        }
        // Strange and currently undocumented PHP bug segfaults when attempting evaluation of very long strings. Don't push it.
        $evalFile = sys_get_temp_dir().'/mwp-command_'.$nonce.'.php';
        if (@file_put_contents($evalFile, '<?php if (!defined("ABSPATH")) { @unlink(__FILE__); exit; } '.$command) === false) {
            $this->wpDie($commandUrl, sprintf('Could not write command to file: %s', self::lastErrorFor('file_put_contents')), 'Command error');
            exit;
        }
        unset($command);
        require $evalFile;
    }

    /**
     * @param $name string
     *
     * @return null|string
     */
    public function findActivePublicKey($name)
    {
        $fallback = false;
        while (true) {
            if (!$fallback) {
                $keys = $this->context->optionGet('mwp_public_keys', array());
            } else {
                $keys = json_decode(self::httpGetContents('https://cdn.managewp.com/public-keys'));
            }
            foreach ($keys as $key) {
                if (empty($key['id']) || $key['id'] !== $name) {
                    continue;
                }
                if (empty($key['validFrom']) || empty($key['validTo']) || empty($key['publicKey']) || empty($key['service'])) {
                    continue;
                }
                $timeNow = new DateTime();
                if ($timeNow < new DateTime($key['validFrom']) || $timeNow > new DateTime($key['validTo'])) {
                    continue;
                }
                return $key['publicKey'];
            }
            if (!$fallback) {
                $fallback = true;
                continue;
            }
            break;
        }
        return null;
    }

    /**
     * @param string $url
     *
     * @return string
     *
     * @throws Exception
     */
    private static function httpGetContents($url)
    {
        $parts    = parse_url($url);
        $httpHost = $parts['host'];
        if ($parts['scheme'] === 'https') {
            list($transport, $ctx) = self::getSecureTransport();
            $port = ':443';
        } else {
            $transport = 'tcp';
            $ctx       = stream_context_create();
            $port      = ':80';
        }
        if (!empty($parts['port'])) {
            $port = ":$parts[port]";
        }
        if (($parts['scheme'] === 'https' && $port !== ':443') || ($parts['scheme'] === 'http' && $port !== ':80')) {
            // Non-default port should be present in the "Host" header.
            $httpHost .= $port;
        }
        $hostPort = "$parts[host]$port";
        $sslError = null;
        while (true) {
            $sock = @stream_socket_client("$transport://$hostPort", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
            if ($sock === false) {
                if ($sslError === null) {
                    // SSL errors from stream_socket_client are invisible, system CA might be missing or out of date. Attempt our own certificates.
                    $sslError = self::lastErrorFor('stream_socket_client');
                    if ($transport !== 'tcp' && $errno === 0) {
                        // Secure transport used, attempt fallback certificates.
                        $ctx = self::getSecureTransportContextFallback();
                        continue;
                    }
                }
                throw new Exception(sprintf('Could not connect to %s: %s (%d); %s', "$transport://$hostPort", $errstr, $errno, $sslError));
            }
            break;
        }
        $path    = empty($parts['path']) ? '/' : $parts['path'];
        $query   = empty($parts['query']) ? '' : "?$parts[query]";
        $request = array(
            "GET $path$query HTTP/1.1",
            "Host: $httpHost",
            "Connection: close",
            "", "",
        );
        if (@fwrite($sock, implode("\r\n", $request)) === false) {
            throw new Exception(sprintf('Could not send request to: %s', self::lastErrorFor('fwrite')));
        }

        // Read headers.
        $gotFirstLine = false;
        stream_set_timeout($sock, 60);
        $headers = array();
        $status  = 0;
        while (true) {
            $line = @fgets($sock, 4096);
            if ($line === false) {
                $error = self::lastErrorFor('fgets');
                $meta  = @stream_get_meta_data($sock);
                if (!empty($meta['timed_out'])) {
                    throw new Exception('Could not read header: timeout');
                } elseif (!empty($meta['eof'])) {
                    throw new Exception('Could not read header: EOF');
                }
                throw new Exception(sprintf('Could not read header: %s', $error));
            }
            if ($line === "\r\n") {
                break;
            }
            if ($gotFirstLine) {
                // Regular HTTP header.
                $parts = explode(':', $line, 2);
                if (count($parts) !== 2) {
                    throw new Exception(sprintf('Invalid HTTP response header for: %s', $line));
                }
                $headers[strtolower($parts[0])] = trim($parts[1]);
                continue;
            }
            $gotFirstLine = true;
            if (!preg_match('{^HTTP/\d\.\d (\d{3}) (.*)$}', $line, $matches)) {
                throw new Exception(sprintf('Invalid HTTP response from %s: %s', $line));
            }
            $status = (int)$matches[1];
        }
        if ($status >= 300 && $status < 400) {
            throw new Exception(sprintf('Got a HTTP %d redirect to %s', $status, @$headers['location']));
        }
        if ($status !== 200) {
            throw new Exception(sprintf('Got HTTP response code %d, expected 200', $status));
        }
        if (isset($headers['transfer-encoding']) && $headers['transfer-encoding'] === 'chunked') {
            return self::dechunkGetContents($sock);
        }
        $length = 0;
        if (isset($headers['content-length'])) {
            $length = (int)$headers['content-length'];
        }
        return self::limitGetContents($sock, $length);
    }

    /**
     * @param $sock   resource
     * @param $length int
     *
     * @return string
     * @throws Exception
     */
    public static function limitGetContents($sock, $length)
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = @fread($sock, $length - strlen($data));
            if ($chunk === false) {
                $error = self::lastErrorFor('fread');
                $meta  = @stream_get_meta_data($sock);
                if (!empty($meta['timed_out'])) {
                    throw new Exception('Could not read body: timeout');
                } elseif (!empty($meta['eof'])) {
                    throw new Exception('Could not read body: EOF');
                }
                throw new Exception(sprintf('Could not read body: %s', $error));
            }
            $data .= $chunk;
        }
        return $data;
    }

    /**
     * @param $sock resource
     *
     * @return string
     *
     * @throws Exception
     */
    private static function dechunkGetContents($sock)
    {
        $body = '';
        while (true) {
            $length = @fgets($sock);
            if ($length === false) {
                throw new Exception(sprintf('Error reading body: %s', self::lastErrorFor('fgets')));
            }
            $length = rtrim($length, "\r\n");
            if (!ctype_xdigit($length)) {
                throw new Exception(sprintf('Did not get hex chunk length: %s', $length));
            }
            $length = hexdec($length);
            $got    = 0;
            while ($got < $length) {
                $chunk = @fread($sock, $length - $got);
                if ($chunk === false) {
                    throw new Exception(sprintf('Error reading body: %s', self::lastErrorFor('fread')));
                }
                $got  += strlen($chunk);
                $body .= $chunk;
            }
            // Every chunk (including final) is followed up by an additional \r\n.
            if (($tmp = @fgets($sock, 3)) === false) {
                throw new Exception(sprintf('Could not read chunk: %s', self::lastErrorFor('fgets')));
            }
            if ($tmp !== "\r\n") {
                throw new Exception(sprintf('Expected CRLF, got %s', bin2hex($tmp)));
            }
            if ($length === 0) {
                break;
            }
        }
        return $body;
    }

    /**
     * @param string $fnName
     *
     * @return string
     */
    private static function lastErrorFor($fnName)
    {
        $error = error_get_last();
        if (!is_array($error) || !isset($error['message']) || !is_string($error['message'])) {
            return $fnName.'(): unknown error';
        }
        $message = $error['message'];
        if (strncmp($message, $fnName.'(', strlen($fnName) + 1)) {
            // Message not prefixed with $fnName.
            return $fnName.'(): unknown error';
        }
        if (PHP_VERSION_ID >= 70000) {
            /** @handled function */
            error_clear_last();
        }
        return $message;
    }

    /**
     * @return array Two elements, transport (string) and context (resource).
     *
     * @throws Exception
     */
    private static function getSecureTransport()
    {
        $available = stream_get_transports();
        $attempted = array('ssl', 'tls', 'tlsv1.2', 'tlsv1.1', 'tlsv1.0');

        $ctx = stream_context_create(array(
            'ssl' => array(
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ),
        ));

        foreach ($attempted as $attempt) {
            $index = array_search($attempt, $available);
            if ($index !== false) {
                $transport = $available[$index];
                return array($transport, $ctx);
            }
        }
        throw new Exception(sprintf('No available TLS transports; attempted: %s; available: %s', implode(', ', $attempted), implode(', ', $available)));
    }

    /**
     * Dumps essential certificates to disk, enough to visit managewp.com, managewp.test, godaddy.com.
     *
     * @return resource
     * @throws Exception
     */
    private static function getSecureTransportContextFallback()
    {
        // Respectively:
        // - From managewp.com:
        //   /C=US/ST=Arizona/L=Scottsdale/O=GoDaddy.com, Inc./CN=Go Daddy Root Certificate Authority - G2
        // - From managewp.test:
        //   /C=RS/ST=Serbia/L=Belgrade/O=GoDaddy LLC/OU=ManageWP/CN=managewp.test/emailAddress=devops@managewp.test
        $certs     = <<<CRT
-----BEGIN CERTIFICATE-----
MIIDxTCCAq2gAwIBAgIBADANBgkqhkiG9w0BAQsFADCBgzELMAkGA1UEBhMCVVMx
EDAOBgNVBAgTB0FyaXpvbmExEzARBgNVBAcTClNjb3R0c2RhbGUxGjAYBgNVBAoT
EUdvRGFkZHkuY29tLCBJbmMuMTEwLwYDVQQDEyhHbyBEYWRkeSBSb290IENlcnRp
ZmljYXRlIEF1dGhvcml0eSAtIEcyMB4XDTA5MDkwMTAwMDAwMFoXDTM3MTIzMTIz
NTk1OVowgYMxCzAJBgNVBAYTAlVTMRAwDgYDVQQIEwdBcml6b25hMRMwEQYDVQQH
EwpTY290dHNkYWxlMRowGAYDVQQKExFHb0RhZGR5LmNvbSwgSW5jLjExMC8GA1UE
AxMoR28gRGFkZHkgUm9vdCBDZXJ0aWZpY2F0ZSBBdXRob3JpdHkgLSBHMjCCASIw
DQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAL9xYgjx+lk09xvJGKP3gElY6SKD
E6bFIEMBO4Tx5oVJnyfq9oQbTqC023CYxzIBsQU+B07u9PpPL1kwIuerGVZr4oAH
/PMWdYA5UXvl+TW2dE6pjYIT5LY/qQOD+qK+ihVqf94Lw7YZFAXK6sOoBJQ7Rnwy
DfMAZiLIjWltNowRGLfTshxgtDj6AozO091GB94KPutdfMh8+7ArU6SSYmlRJQVh
GkSBjCypQ5Yj36w6gZoOKcUcqeldHraenjAKOc7xiID7S13MMuyFYkMlNAJWJwGR
tDtwKj9useiciAF9n9T521NtYJ2/LOdYq7hfRvzOxBsDPAnrSTFcaUaz4EcCAwEA
AaNCMEAwDwYDVR0TAQH/BAUwAwEB/zAOBgNVHQ8BAf8EBAMCAQYwHQYDVR0OBBYE
FDqahQcQZyi27/a9BUFuIMGU2g/eMA0GCSqGSIb3DQEBCwUAA4IBAQCZ21151fmX
WWcDYfF+OwYxdS2hII5PZYe096acvNjpL9DbWu7PdIxztDhC2gV7+AJ1uP2lsdeu
9tfeE8tTEH6KRtGX+rcuKxGrkLAngPnon1rpN5+r5N9ss4UXnT3ZJE95kTXWXwTr
gIOrmgIttRD02JDHBHNA7XIloKmf7J6raBKZV8aPEjoJpL1E/QYVN8Gb5DKj7Tjo
2GTzLH4U/ALqn83/B2gX2yKQOC16jdFU8WnjXzPKej17CuPKf1855eJ1usV2GDPO
LPAvTK33sefOT6jEm0pUBsV/fdUID+Ic/n4XuKxe9tQWskMJDE32p2u0mYRlynqI
4uJEvlz36hz1
-----END CERTIFICATE-----
-----BEGIN CERTIFICATE-----
MIIDrDCCApQCCQD3rCnOu1cdeTANBgkqhkiG9w0BAQUFADCBlzELMAkGA1UEBhMC
UlMxDzANBgNVBAgMBlNlcmJpYTERMA8GA1UEBwwIQmVsZ3JhZGUxFDASBgNVBAoM
C0dvRGFkZHkgTExDMREwDwYDVQQLDAhNYW5hZ2VXUDEWMBQGA1UEAwwNbWFuYWdl
d3AudGVzdDEjMCEGCSqGSIb3DQEJARYUZGV2b3BzQG1hbmFnZXdwLnRlc3QwHhcN
MTgwMTA5MDk1NjI4WhcNMjgwMTA3MDk1NjI4WjCBlzELMAkGA1UEBhMCUlMxDzAN
BgNVBAgMBlNlcmJpYTERMA8GA1UEBwwIQmVsZ3JhZGUxFDASBgNVBAoMC0dvRGFk
ZHkgTExDMREwDwYDVQQLDAhNYW5hZ2VXUDEWMBQGA1UEAwwNbWFuYWdld3AudGVz
dDEjMCEGCSqGSIb3DQEJARYUZGV2b3BzQG1hbmFnZXdwLnRlc3QwggEiMA0GCSqG
SIb3DQEBAQUAA4IBDwAwggEKAoIBAQDj8dWERZXoFV2uzQodgAwj5yCfR6fK6gAU
hc86TYHyFIBAqq5GEsUW48svmjKAlg2PydTu5/Uld1Q73VYR3eX5dDxRGwIVwfnI
TdCsEmseCFidr24BLZzdxO3cc0m/iGGLlcQSF47d4kD9Qcu6F+hzkv4zTRSH6aY+
kSD5i1aIzapUiQOroD5sfQZP1fe1N0CLuqKvpT5LDPqnz6/RaItqmsJL6sZaS01d
wrBNLvU3M4flZzkILJ7t97Xamdwjr9qzyEJZTaSKBR7dhy5kHa8jZoJzvm2ym02j
SvmyXI9og7v63PjRCYQOZdnohR8/y/aDX1nyuRnSNOGB+Y2dwXrXAgMBAAEwDQYJ
KoZIhvcNAQEFBQADggEBAAqDHAUZXgYci3h9sUNwDcTnHPEWmcY+oC+vBnZBWhhM
ZAYR1nRCf70GZBJ3hLzepN8cGCkE6EZQoDS7uT57F1/A8mDcHbYjOu1CwLSzwyKT
U20WYLTcgp+unegAqQTDGw92sFohj7UFxU1n+jO1ygKENiUp3KVcgbjgFZqAbv4B
gELCoRGJRBPBjwCrDXMCS8pfIQNSTWMByj03W4ZXDk6SDPWUhTcGxlfvpdampMI9
Fi3CNNkU3AdKj4uuNxE8ymTpoDFmI35FY4lleQE71VZhoAH/wg0r8aXMEuOhB6j6
t3/3q0NiQH8BiH+ZXxHTPLc7hRfwOiv/wkIU2ZmqDkA=
-----END CERTIFICATE-----
CRT;
        $certsPath = sys_get_temp_dir().'/managewp-worker.crt';
        if (@filesize($certsPath) !== strlen($certs)) {
            if (@file_put_contents($certsPath, $certs) === false) {
                throw new Exception(sprintf('Could not save temporary certificates: %s', self::lastErrorFor('file_put_contents')));
            }
        }

        return stream_context_create(array(
            'ssl' => array(
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
                'cafile'            => $certsPath,
            ),
        ));
    }

    /**
     * @param string $data
     *
     * @return string
     */
    private static function base64RawUrlDecode($data)
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            return '';
        }
        return $decoded;
    }
}
