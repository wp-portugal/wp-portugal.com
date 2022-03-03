<?php

if ( !defined( 'ABSPATH' ) )
   exit;

include_once ( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' );

class CF7GSC_googlesheet {

   private $token;
   private $spreadsheet;
   private $worksheet;

   const clientId = '1075324102277-drjc21uouvq2d0l7hlgv3bmm67er90mc.apps.googleusercontent.com';
   const clientSecret = 'RFM9hElCqJMsXyc8YNjhf9Zs';
   const redirect = 'urn:ietf:wg:oauth:2.0:oob';

   private static $instance;

   public function __construct() {
      
   }

   public static function setInstance( Google_Client $instance = null ) {
      self::$instance = $instance;
   }

   public static function getInstance() {
      if ( is_null( self::$instance ) ) {
         throw new LogicException( "Invalid Client" );
      }

      return self::$instance;
   }

   //constructed on call
   public static function preauth( $access_code ) {
      $client = new Google_Client();
      $client->setClientId( CF7GSC_googlesheet::clientId );
      $client->setClientSecret( CF7GSC_googlesheet::clientSecret );
      $client->setRedirectUri( CF7GSC_googlesheet::redirect );
      $client->setScopes( Google_Service_Sheets::SPREADSHEETS );
      $client->setScopes( Google_Service_Drive::DRIVE_METADATA_READONLY );
      $client->setAccessType( 'offline' );
      $client->fetchAccessTokenWithAuthCode( $access_code );
      $tokenData = $client->getAccessToken();

      CF7GSC_googlesheet::updateToken( $tokenData );
   }

   public static function updateToken( $tokenData ) {
      $expires_in = isset( $tokenData['expires_in'] ) ? intval( $tokenData['expires_in'] ) : 0;
      $tokenData['expire'] = time() + $expires_in;
      try {
         $tokenJson = json_encode( $tokenData );
         update_option( 'gs_token', $tokenJson );
      } catch ( Exception $e ) {
         Gs_Connector_Utility::gs_debug_log( "Token write fail! - " . $e->getMessage() );
      }
   }

   public function auth() {
      $tokenData = json_decode( get_option( 'gs_token' ), true );
      if ( !isset( $tokenData['refresh_token'] ) || empty( $tokenData['refresh_token'] ) ) {
         throw new LogicException( "Auth, Invalid OAuth2 access token" );
         exit();
      }

      try {
         $client = new Google_Client();
         $client->setClientId( CF7GSC_googlesheet::clientId );
         $client->setClientSecret( CF7GSC_googlesheet::clientSecret );
         $client->setScopes( Google_Service_Sheets::SPREADSHEETS );
         $client->setScopes( Google_Service_Drive::DRIVE_METADATA_READONLY );
         $client->refreshToken( $tokenData['refresh_token'] );
         $client->setAccessType( 'offline' );
         CF7GSC_googlesheet::updateToken( $tokenData );

         self::setInstance( $client );
      } catch ( Exception $e ) {
         throw new LogicException( "Auth, Error fetching OAuth2 access token, message: " . $e->getMessage() );
         exit();
      }
   }

   public function get_user_data() {
      $client = self::getInstance();

      $results = $this->get_spreadsheets();

      echo '<pre>';
      print_r( $results );
      echo '</pre>';
      $spreadsheets = $this->get_worktabs( '1mRuDMnZveDFQrmzHM9s5YkPA4F_dZkHJ1Gh81BvYB2k' );
      echo '<pre>';
      print_r( $spreadsheets );
      echo '</pre>';
      $this->setSpreadsheetId( '1mRuDMnZveDFQrmzHM9s5YkPA4F_dZkHJ1Gh81BvYB2k' );
      $this->setWorkTabId( 'Foglio1' );
      $worksheetTab = $this->list_rows();
      echo '<pre>';
      print_r( $worksheetTab );
      echo '</pre>';
   }

   //preg_match is a key of error handle in this case
   public function setSpreadsheetId( $id ) {
      $this->spreadsheet = $id;
   }

   public function getSpreadsheetId() {

      return $this->spreadsheet;
   }

   public function setWorkTabId( $id ) {
      $this->worksheet = $id;
   }

   public function getWorkTabId() {
      return $this->worksheet;
   }

   public function add_row( $data ) {
      try {
         $client = self::getInstance();
         $service = new Google_Service_Sheets( $client );
         $spreadsheetId = $this->getSpreadsheetId();
         $work_sheets = $service->spreadsheets->get( $spreadsheetId );

         if ( !empty( $work_sheets ) && !empty( $data ) ) {
            foreach ( $work_sheets as $sheet ) {
               $properties = $sheet->getProperties();
               $sheet_id = $properties->getSheetId();

               $worksheet_id = $this->getWorkTabId();

               if ( $sheet_id == $worksheet_id ) {
                  $worksheet_id = $properties->getTitle();
                  $worksheetCell = $service->spreadsheets_values->get( $spreadsheetId, $worksheet_id . "!1:1" );
                  $insert_data = array();
                  if ( isset( $worksheetCell->values[0] ) ) {
                     foreach ( $worksheetCell->values[0] as $k => $name ) {
                        if ( isset( $data[$name] ) && $data[$name] != '' ) {
                           $insert_data[] = $data[$name];
                        } else {
                           $insert_data[] = '';
                        }
                     }
                  }
				  
				  /*RASHID*/
					$tab_name = $worksheet_id;
					$full_range = $tab_name."!A1:Z";
					$response   = $service->spreadsheets_values->get( $spreadsheetId, $full_range );
					$get_values = $response->getValues();
					
					if( $get_values) {
						$row  = count( $get_values ) + 1;
					}
					else {
						$row = 1;
					}
					$range = $tab_name."!A".$row.":Z"; 
				  
                  $range_new = $worksheet_id;

                  // Create the value range Object
                  $valueRange = new Google_Service_Sheets_ValueRange();

                  // set values of inserted data
                  $valueRange->setValues( ["values" => $insert_data ] );
                  
                  // Add two values
                  // Then you need to add configuration
                  //$conf = ["valueInputOption" => "USER_ENTERED"];
                  $conf = ["valueInputOption" => "USER_ENTERED"];

                  // append the spreadsheet(add new row in the sheet)
                  $result = $service->spreadsheets_values->append( $spreadsheetId, $range, $valueRange, $conf );
               }
            }
         }
      } catch ( Exception $e ) {
         return null;
         exit();
      }
   }

   public function add_multiple_row( $data ) {
      try {
         $client = self::getInstance();
         $service = new Google_Service_Sheets( $client );
         $spreadsheetId = $this->getSpreadsheetId();
         $work_sheets = $service->spreadsheets->get( $spreadsheetId );

         if ( !empty( $work_sheets ) && !empty( $data ) ) {
            foreach ( $work_sheets as $sheet ) {
               $properties = $sheet->getProperties();
               $sheet_id = $properties->getSheetId();

               $worksheet_id = $this->getWorkTabId();

               if ( $sheet_id == $worksheet_id ) {
                  $worksheet_id = $properties->getTitle();
                  $worksheetCell = $service->spreadsheets_values->get( $spreadsheetId, $worksheet_id . "!1:1" );
                  $insert_data = array();
                  $final_data = array();
                  if ( isset( $worksheetCell->values[0] ) ) {
                     foreach ( $data as $key => $value ) {
                        foreach ( $worksheetCell->values[0] as $k => $name ) {
                           if ( isset( $value[$name] ) && $value[$name] != '' ) {
                              $insert_data[] = $value[$name];
                           } else {
                              $insert_data[] = '';
                           }
                        }
                        $final_data[] = $insert_data;
                        unset( $insert_data );
                     }
                  }

                  //$range_new = $worksheet_id;
                  
                  /*RASHID*/
					$tab_name = $worksheet_id;
					$full_range = $tab_name."!A1:Z";
					$response   = $service->spreadsheets_values->get( $spreadsheetId, $full_range );
					$get_values = $response->getValues();
					
					if( $get_values) {
						$row  = count( $get_values ) + 1;
					}
					else {
						$row = 1;
					}
					$range = $tab_name."!A".$row.":Z";

                  $sheet_values = $final_data;

                  if ( !empty( $sheet_values ) ) {
                     $requestBody = new Google_Service_Sheets_ValueRange( [
                        'values' => $sheet_values
                             ] );

                     $params = [
                        'valueInputOption' => 'USER_ENTERED'
                     ];
                     $response = $service->spreadsheets_values->append( $spreadsheetId, $range, $requestBody, $params );
                  }
               }
            }
         }
      } catch ( Exception $e ) {
         return null;
         exit();
      }
   }
   
   //get all the spreadsheets
   public function get_spreadsheets() {
      $all_sheets = array();
      try {
         $client = self::getInstance();

         $service = new Google_Service_Drive( $client );

         $optParams = array(
            'q' => "mimeType='application/vnd.google-apps.spreadsheet'"
         );
         $results = $service->files->listFiles( $optParams );
         foreach ( $results->files as $spreadsheet ) {
            if ( isset( $spreadsheet['kind'] ) && $spreadsheet['kind'] == 'drive#file' ) {
               $all_sheets[] = array(
                  'id' => $spreadsheet['id'],
                  'title' => $spreadsheet['name'],
               );
            }
         }
      } catch ( Exception $e ) {
         return null;
         exit();
      }
      return $all_sheets;
   }

   //get worksheets title
   public function get_worktabs( $spreadsheet_id ) {
      $work_tabs_list = array();
      try {
         $client = self::getInstance();
         $service = new Google_Service_Sheets( $client );
         $work_sheets = $service->spreadsheets->get( $spreadsheet_id );


         foreach ( $work_sheets as $sheet ) {
            $properties = $sheet->getProperties();
            $work_tabs_list[] = array(
               'id' => $properties->getSheetId(),
               'title' => $properties->getTitle(),
            );
         }
      } catch ( Exception $e ) {
         return null;
         exit();
      }

      return $work_tabs_list;
   }

   /**
    * Function - Adding custom column header to the sheet
    * @param string $sheet_name
    * @param string $tab_name
    * @param array $gs_map_tags 
    * @since 1.0
    */
   public function add_header( $sheetname, $tabname, $final_header_array, $old_header ) {
      $client = self::getInstance();
      $service = new Google_Service_Sheets( $client );
      $spreadsheetId = $this->getSpreadsheetId();
      $work_sheets = $service->spreadsheets->get( $spreadsheetId );

      $field_tag_array[] = '';
      if ( !empty( $work_sheets ) ) {
         foreach ( $work_sheets as $sheet ) {

            $properties = $sheet->getProperties();
            $sheet_id = $properties->getSheetId();
            $worksheet_id = $this->getWorkTabId();
            if ( $sheet_id == $worksheet_id ) {
               $worksheet_title = $properties->getTitle();
               $field_tag = isset( $_POST['gf-custom-ck'] ) ? $_POST['gf-custom-ck'] : array();
               $field_tag_key = isset( $_POST['gf-custom-header-key'] ) ? $_POST['gf-custom-header-key'] : "";
               $field_tag_placeholder = isset( $_POST['gf-custom-header-placeholder'] ) ? $_POST['gf-custom-header-placeholder'] : "";
               $field_tag_column = isset( $_POST['gf-custom-header'] ) ? $_POST['gf-custom-header'] : "";
               if ( !empty( $field_tag ) ) {
                  foreach ( $field_tag as $key => $value ) {
                     $gf_key = $field_tag_key[$key];
                     $gf_val = (!empty( $field_tag_column[$key] ) ) ? $field_tag_column[$key] : $field_tag_placeholder[$key];
                     if ( $gf_val !== "" ) {
                        $field_tag_array[$gf_key] = $gf_val;
                        $gravityform_tags[] = $gf_val;
                     }
                  }
               }
               $range = $worksheet_title . '!1:1';

               $values = array( array_values( array_filter( $field_tag_array ) ) );

               $count_old_header = (!empty($old_header)) ? (count($old_header)) : 0;
               $count_new_header = (!empty($final_header_array)) ? (count($final_header_array)) : 0;
               $data_values = array();

// If old header count is greater than new header count than empty the header
               if ( $count_old_header !== 0 && $count_old_header > $count_new_header ) {
                  for ( $i = 0; $i <= $count_old_header; $i++ ) {
                     $column_name = isset( $final_header_array[$i] ) ? $final_header_array[$i] : "";
                     if ( $column_name !== "" ) {
                        $data_values[] = $column_name;
                     } else {
                        $data_values[] = "";
                     }
                  }
               } else {

                  foreach ( $final_header_array as $column_name ) {
                     $data_values[] = $column_name;
                  }
               }

               $values = array( $data_values );


               $requestBody = new Google_Service_Sheets_ValueRange( [
                  'values' => $values
               ] );

               $params = [
                  'valueInputOption' => 'USER_ENTERED'
               ];
               $response = $service->spreadsheets_values->update( $spreadsheetId, $range, $requestBody, $params );
            }
         }
      }
   }
	
	
	
	public function gsheet_print_google_account_email() {		
		try{
         $google_account = get_option("cf7gf_email_account");
         if( $google_account ) {
            return $google_account;
         }
         else {
            
            $google_sheet = new CF7GSC_googlesheet();
            $google_sheet->auth();				 
            $email = $google_sheet->gsheet_get_google_account_email();
            
            return $email;
         }
      }catch(Exception $e){
         return false;
      }   		
	}
	
	public function gsheet_get_google_account_email() {		
		$google_account = $this->gsheet_get_google_account();	
		
		if( $google_account ) {
			return $google_account->email;
		}
		else {
			return "";
		}
	}
	
	public function gsheet_get_google_account() {		
	
		try {
			$client = $this->getInstance();
			
			if( ! $client ) {
				return false;
			}
			
			$service = new Google_Service_Oauth2($client);
			$user = $service->userinfo->get();			
		}
		catch (Exception $e) {
			Gs_Connector_Utility::gs_debug_log( __METHOD__ . " Error in fetching user info: \n " . $e->getMessage() );
			return false;
		}
		
		return $user;
	}
	
}
