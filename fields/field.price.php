<?php
	Class fieldPrice extends Field {
		
		private $_validation_rule;
		private $_default_locale;
		private $_default_format;
		
		public function __construct(&$parent) {
			parent::__construct($parent);
			$this->_name = __('Price');
			$this->_required = true;
			$this->_validation_rule = '/^\d+(\.\d{2})?$/';
			$this->_default_locale = 'en_US';
			$this->_default_format = '%i';
			
			$this->set('required', 'no');
			$this->set('locale', $this->_default_locale);
			$this->set('format', $this->_default_format);
			
		}
		
		public function allowDatasourceOutputGrouping() {
			return true;
		}
		
		public function allowDatasourceParamOutput() {
			return true;
		}

		public function isSortable() {
			return true;
		}
		
		public function canFilter() {
			return true;
		}
		
		public function canImport() {
			return true;
		}

		public function canPrePopulate() {
			return true;
		}
	

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function setFromPOST($postdata){
			parent::setFromPOST($postdata);
			if($this->get('locale') == '') $this->set('locale', $this->_default_locale);
			if($this->get('format') == '') $this->set('format', $this->_default_format);
		}

		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			$div = new XMLElement('div', NULL, array('class' => 'group'));
			$this->__appendLocaleInput($div);
			$this->__appendFormatInput($div);
			$wrapper->appendChild($div);

			$div = new XMLElement('div', NULL, array('class' => 'compact'));
			$this->appendRequiredCheckbox($div);
			$this->appendShowColumnCheckbox($div);
			$wrapper->appendChild($div);
		}

		public function commit(){
			if(!parent::commit()) return false;
			$id = $this->get('id');
			if($id === false) return false;

			$fields = array(
				'field_id'	=> $id,
				'locale'	=> $this->get('locale'),
				'format'	=> $this->get('format')
			);

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

		
	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {
			$value = General::sanitize($data['value']);
			$label = Widget::Label($this->get('label'));
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, $value));
			$label->appendChild(new XMLElement('em', __('Enter currency in the following format: ####.## (for example: 49.95, 1900, 1899.50)')));
			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
		}
		
		public function checkPostFieldData($data, &$message, $entry_id=NULL) {
			$message = NULL;
			if($this->get('required') == 'yes' && strlen(trim($data)) == 0){
				$message = __("'%s' is a required field.", array($this->get('label')));
				return self::__MISSING_FIELDS__;
			}

			if(!$this->__applyValidationRules($data)){
				$message = __("'%s' contains invalid data. Please check the contents.", array($this->get('label')));
				return self::__INVALID_FIELDS__;
			}

			return self::__OK__;
		}
		
		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {
			$status = self::__OK__;
			if (strlen(trim($data)) == 0) return array();
			return array('value' => $data);
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/
		
		public function appendFormattedElement(&$wrapper, $data, $encode=false) {
			setlocale(LC_MONETARY, $this->get('locale'));
			
			$wrapper->appendChild(
				new XMLElement(
					$this->get('element_name'), 
					General::sanitize(money_format($this->get('format'), $data['value'])),
					array('raw' => $data['value'])
				)
			);
		}
		
		public function prepareTableValue($data, XMLElement $link = null) {
			if (empty($data)) return;
			
			setlocale(LC_MONETARY, $this->get('locale'));
			$value = General::sanitize(money_format($this->get('format'), $data['value']));
			
			if ($link) {
				$link->setValue($value);
				return $link->generate();
			}

			return $value;			
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->get('id');
			
			 if (preg_match('/^range:/i', $data[0])) {

					$field_id = $this->get('id');
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";

					$values = explode('/', trim(substr($data[0], 6)));
					$min = (!empty($values[0]) && is_numeric($values[0])) ? $values[0] : null;
					$max = (!empty($values[1]) && is_numeric($values[1])) ? $values[1] : null;
					
					if($min && $max) {
						$where .= " AND `t$field_id`.`value` BETWEEN $min AND $max";
					} else {
						if ($min) $where .= " AND `t$field_id`.`value` >= $min";
						if ($max) $where .= " AND `t$field_id`.`value` <= $max";
					}
					

			} elseif (self::isFilterRegex($data[0])) {
				$this->_key++;
				$pattern = str_replace('regexp:', '', $this->cleanValue($data[0]));
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND (
						t{$field_id}_{$this->_key}.value REGEXP '{$pattern}'
					)
				";
			} elseif ($andOperation) {
				foreach ($data as $value) {
					$this->_key++;
					$value = $this->cleanValue($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
							ON (e.id = t{$field_id}_{$this->_key}.entry_id)
					";
					$where .= "
						AND (
							t{$field_id}_{$this->_key}.value = '{$value}'
						)
					";
				}
			} else {
				if (!is_array($data)) $data = array($data);
				
				foreach ($data as &$value) {
					$value = $this->cleanValue($value);
				}
				
				$this->_key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND (
						t{$field_id}_{$this->_key}.value IN ('{$data}')
					)
				";
			}
			return true;
		}
		
		public function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			$wrapper->appendChild(new XMLElement('h4', $this->get('label') . ' <i>'.$this->Name().'</i>'));
			$label = Widget::Label('Value');
			$label->appendChild(Widget::Input('fields[filter]'.($fieldnamePrefix ? '['.$fieldnamePrefix.']' : '').'['.$this->get('id').']'.($fieldnamePostfix ? '['.$fieldnamePostfix.']' : ''), ($data ? General::sanitize($data) : NULL)));
			$wrapper->appendChild($label);

			$wrapper->appendChild(new XMLElement('p', 'To filter by ranges, use `<code>range:{$min}/{$max}</code>` syntax', array('class' => 'help')));

		}

	/*-------------------------------------------------------------------------
		Grouping:
	-------------------------------------------------------------------------*/

		public function groupRecords($records){
			if(!is_array($records) || empty($records)) return;

			$groups = array($this->get('element_name') => array());

			foreach($records as $r){
				$data = $r->getData($this->get('id'));

				$value = General::sanitize($data['value']);
				$handle = Lang::createHandle($value);

				if(!isset($groups[$this->get('element_name')][$handle])){
					setlocale(LC_MONETARY, $this->get('locale'));
					
					$groups[$this->get('element_name')][$handle] = array(
						'attr' => array('handle' => $handle, 'value' => General::sanitize(money_format($this->get('format'), $data['value']))),
						'records' => array(),
						'groups' => array()
					);
				}

				$groups[$this->get('element_name')][$handle]['records'][] = $r;

			}

			return $groups;
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public function createTable() {
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `value` (`value`)
				) ENGINE=MyISAM;"
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function getParameterPoolValue(array $data, $entry_id=NULL){
			return $data['value'];
		}

		private function __appendLocaleInput(XMLElement &$wrapper){
			$label = Widget::Label(__('Locale'));
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][locale]', $this->get('locale')));
			$wrapper->appendChild($label);

		}

		private function __appendFormatInput(XMLElement &$wrapper){
			$label = Widget::Label(__('Format'));
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][format]', $this->get('format')));
			$wrapper->appendChild($label);

		}

		private function __applyValidationRules($data){
			return ($this->_validation_rule ? General::validateString($data, $this->_validation_rule) : true);
		}

	}
