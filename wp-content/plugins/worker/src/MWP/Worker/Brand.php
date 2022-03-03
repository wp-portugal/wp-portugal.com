<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Worker_Brand
{
    const OPTION_NAME = 'mwp_worker_brand';

    private $context;

    /**
     * @var bool
     */
    private $active;

    /**
     * @var string|null
     */
    private $name;

    /**
     * @var string|null
     */
    private $description;

    /**
     * @var string|null
     */
    private $author;

    /**
     * @var string|null
     */
    private $authorUrl;

    /**
     * Hide the plugin from the plugin list.
     *
     * @var bool
     */
    private $hide = false;

    /**
     * Prevent the user from updating/installing plugins and themes within the site. Also disable the plugin and theme code editor.
     *
     * @var bool
     */
    private $disallowEdit = false;

    /**
     * Disable code editor only
     *
     * @var bool
     */
    private $disableCodeEditor = false;

    /**
     * One of the CONTACT_TYPE_* constants.
     *
     * @var int
     */
    private $contactType = 0;

    /**
     * Disable "Contact Support" modal.
     */
    const CONTACT_TYPE_NONE = 0;

    /**
     * Show both text ('textForClient') and the form that submits to the brand owner email address ('adminEmail').
     */
    const CONTACT_TYPE_TEXT_PLUS_FORM = 1;

    /**
     * Show only text ('textForClient').
     */
    const CONTACT_TYPE_TEXT = 2;

    /**
     * Text shown in the "Contact Support" dialog.
     *
     * @var string|null
     */
    private $textForClient;

    /**
     * Email address of the brand owner.
     *
     * @var string|null
     */
    private $adminEmail;

    /**
     * Whether or not the worker branding was sent from Orion.
     *
     * @var bool|null
     */
    private $fromOrion;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
        $brand         = $context->optionGet(self::OPTION_NAME);

        if (!is_array($brand)) {
            return;
        }

        $this->name        = empty($brand['name']) ? null : $brand['name'];
        $this->description = empty($brand['desc']) ? null : $brand['desc'];
        $this->author      = empty($brand['author']) ? null : $brand['author'];
        $this->authorUrl   = empty($brand['author_url']) ? null : $brand['author_url'];
        $this->hide        = isset($brand['hide']) ? $brand['hide'] : $this->hide;
        // "Dissalow" [sic] edit .
        $this->disallowEdit      = isset($brand['dissalow_edit']) ? $brand['dissalow_edit'] : $this->disallowEdit;
        $this->textForClient     = empty($brand['text_for_client']) ? null : $brand['text_for_client'];
        $this->contactType       = isset($brand['email_or_link']) ? (int)$brand['email_or_link'] : self::CONTACT_TYPE_NONE;
        $this->adminEmail        = empty($brand['admin_email']) ? null : $brand['admin_email'];
        $this->fromOrion         = empty($brand['from_orion']) ? false : $brand['from_orion'];
        $this->disableCodeEditor = isset($brand['disable_code_editor']) ? $brand['disable_code_editor'] : $this->disableCodeEditor;

        $this->active = isset($brand['active']) ? $brand['active'] : (bool) ($this->name || $this->description || $this->author || $this->authorUrl || $this->hide || $this->disallowEdit || $this->disableCodeEditor || $this->contactType);
    }

    public function isActive()
    {
        return $this->active;
    }

    /**
     * @return null|string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return null|string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return null|string
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @return null|string
     */
    public function getAuthorUrl()
    {
        return $this->authorUrl;
    }

    /**
     * @return boolean
     */
    public function isHide()
    {
        return $this->hide;
    }

    /**
     * @return boolean
     */
    public function isDisallowEdit()
    {
        return $this->disallowEdit;
    }

    /**
     * @return boolean
     */
    public function isDisableCodeEditor()
    {
        return $this->disableCodeEditor;
    }

    /**
     * @return int
     */
    public function getContactType()
    {
        return $this->contactType;
    }

    /**
     * @return null|string
     */
    public function getTextForClient()
    {
        return $this->textForClient;
    }

    /**
     * @return null|string
     */
    public function getAdminEmail()
    {
        return $this->adminEmail;
    }

    /**
     * @return null|bool
     */
    public function getFromOrion()
    {
        return $this->fromOrion;
    }

    /**
     * Active-record-style delete.
     */
    public function delete()
    {
        $this->context->optionDelete(self::OPTION_NAME);
    }

    /**
     * Active-record-style update.
     *
     * @param array $brand
     */
    public function update(array $brand)
    {
        $this->context->optionSet(self::OPTION_NAME, $brand);
    }
}
