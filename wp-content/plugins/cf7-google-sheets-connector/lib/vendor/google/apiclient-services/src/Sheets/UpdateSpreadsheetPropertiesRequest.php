<?php
/*
 * Copyright 2014 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations under
 * the License.
 */

namespace Google\Service\Sheets;

class UpdateSpreadsheetPropertiesRequest extends \Google\Model
{
  public $fields;
  protected $propertiesType = SpreadsheetProperties::class;
  protected $propertiesDataType = '';

  public function setFields($fields)
  {
    $this->fields = $fields;
  }
  public function getFields()
  {
    return $this->fields;
  }
  /**
   * @param SpreadsheetProperties
   */
  public function setProperties(SpreadsheetProperties $properties)
  {
    $this->properties = $properties;
  }
  /**
   * @return SpreadsheetProperties
   */
  public function getProperties()
  {
    return $this->properties;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(UpdateSpreadsheetPropertiesRequest::class, 'Google_Service_Sheets_UpdateSpreadsheetPropertiesRequest');
