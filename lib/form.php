<?php
require_once dirname(__FILE__).'/tr.php';

class RegistrationForm {
	private $lang;
	private $valid = true;
	private $errors = array();
	private $data;
	private $entityType;

	public function __construct($lang, $data = null) {
		$this->lang = $lang;
		$this->data = $this->clean($data);
	}

	public function isValid($param = null) {
		if ($param === null)
			return $this->valid && count($this->errors) == 0;	

		return !array_key_exists($param, $this->errors);
	}

	public function getErrors() {
		return $this->errors;	
	}

	public function trError($e) {
		global $TR;

		return $TR[$this->lang]['form']['errors'][$e];
	}

	public function printErrors($response = null) {
		virtual('./hlavicka.html');
		echo '<form action="./send.php" method="post">';

		if ($response) {
			echo '<div class="alert alert-danger" role="alert">';
			echo $this->trError('REGFAILED').': '.$response->getMessage();
			echo '</div>';

			foreach ($response->getErrors() as $param => $errors) {
				$tr = $this->trError($error);
				echo '<div class="alert alert-danger" role="alert">';
				echo $this->getLabel($param).': '.implode(', ', $errors);
				echo '</div>';
			}
		}

		foreach ($this->errors as $field => $errors) {
			echo '<div class="alert alert-danger" role="alert">';
			echo $this->getLabel($field).': '.implode('; ', $errors);
			echo '</div>';
		}

		echo '<input type="hidden" name="entity_type" value="'.$this->getEntityType().'">';

		$f = $this;
		include $this->getEntityType().'-osoba/form.php';

		echo '</form>';
		virtual('./paticka.html');
	}

	public function getLabel($field) {
		global $TR;

		return $TR[$this->lang]['form']['fields'][$field];
	}

	public function getData() {
		return $this->data;
	}

	public function getEntityType() {
		return $this->entityType;
	}

	public function isValidationTest() {
		return array_key_exists('_mock', $this->data) && $this->data['_mock'];
	}

	public function input($name, $type = 'text', $attrs = array()) {
		$ret = '<input
			type="'.$type.'"
			id="'.$name.'"
			name="'.$name.'"
			placeholder="'.$this->getLabel($name).'"
			value="'.htmlspecialchars(isset($_POST[$name]) ? $_POST[$name] : '').'"
			class="form-control '.($this->isValid($name) ? '' : 'error').'" ';

		foreach ($attrs as $k => $v)
			$ret .= $k.'="'.$v.'" ';

		$ret .= '>';
		echo $ret;
	}

	public function select($name, $values) {
		if ($_POST[$name] && array_key_exists($_POST[$name], $values))
			$selected = $_POST[$name];

		else
			$selected = null;

		$ret = "<select
			class=\"form-control ".($this->isValid($name) ? '' : 'error')."\"
			id=\"$name\"
			name=\"$name\">\n";
		$ret .= "\t<option disabled ".($selected ? '' : 'selected').">".$this->getLabel($name)."</option>\n";

		foreach ($values as $k => $v)
			$ret .= "\t<option value=\"$k\" ".($selected == $k ? 'selected' : '').">$v</option>\n";

		$ret .= "</select>\n";

		echo $ret;
	}

	public function clean($data) {
		if (!$data)
			return false;

		$ret = array();

		foreach ($data as $k => $v)
			$ret[$k] = trim($v);

		return $ret;
	}

	public function validate() {
		if (!$this->data)
			return $this->valid = false;

		$entities = array('fyzicka', 'pravnicka');

		if (in_array($this->data['entity_type'], $entities))
			$this->entityType = $this->data['entity_type'];

		else
			$this->entityType = 'fyzicka';

		$fields = array(
			'login',
			'name',
			'email',
			'birth',
			'address',
			'city',
			'zip',
			'country',
			'how',
			'note',
			'distribution',
			'location',
			'currency',
		);

		if ($this->data['entity_type'] === 'pravnicka') {
			$fields[] = 'org_name';
			$fields[] = 'ic';
		}
		
		$v = new Validators($this->lang, $fields);
		$v->validate($this->data);

		foreach ($v->errors as $field => $errors) {
			$this->errors[$field] = array();

			foreach ($errors as $e)
				$this->errors[$field][] = $this->trError($e);
		}
	}
}

class Validators {
	public $errors = array();
	public $fields;
	private $db;

	public function __construct($lang, $fields) {
		$this->lang = $lang;
		$this->fields = $fields;
	}

	public function validate($data) {
		foreach ($this->fields as $f) {
			if (!method_exists($this, $f))
				continue;

			$ret = call_user_func(array($this, $f), $data[$f]);

			if ($ret === true || (is_array($ret) && count($ret) == 0))
				continue;

			if (is_array($ret))
				$this->errors[$f] = $ret;

			else
				$this->errors[$f] = array($ret);
		}
	}

	public function login($v) {
		$ret = array();

		if (!preg_match('/^[a-zA-Z0-9\-\.]{2,63}$/', $v))
			$ret[] = 'NOTLOGIN';

		if (preg_match('/\.\./', $v))
			$ret[] = 'TWODOTS';

		if (preg_match('/--/', $v))
			$ret[] = 'TWOHYPHENS';

		return $ret;
	}

	public function name($v) {
		$ret = array();

		if (strlen($v) < 2)
			$ret[] = 'LEN_2';

		if (preg_match('/\d/', $v))
			$ret[] = 'NOTNUM';

		return $ret;
	}

	public function email($v) {
		if (
		 	preg_match('/\s/', $v) ||
			!preg_match('/[^@]+@[a-zA-Z0-9_-]+\.[a-z]+/', $v) ||
			preg_match('/\.\./', $v)
		)
			return 'NOTMAIL';

		// Test whether this mail uses MS mail servers
		$domain = substr($v, strpos($v, '@') + 1);
		$mail_servers = dns_get_record($domain, DNS_MX);

		if ($mail_servers === false) {
			// No MX record means MS server is not used
			return true;
		}

		foreach ($mail_servers as $mail_server) {
			if (preg_match('/hotmail\.com$/', $mail_server['target']))
				return 'EHOTMAIL';
		}

		return true;
	}

	public function birth($v) {
		$ret = array();
		$y = date('Y');

		if (preg_match('/\D/', $v))
			$ret[] = 'NUMONLY';

		if ($v < ($y - 100) || ($y - $v) <= 5)
			$ret[] = 'NOTBIRTH';

		return $ret;
	}
	
	public function address($v) {
		$ret = array();

		if (strlen($v) < 2)
			$ret[] = 'LEN_2';

		# No more validations for English form
		if ($this->lang === 'en')
			return $ret;

		if (!preg_match('/\d/', $v))
			$ret[] = 'NOHOUSEN';

		return $ret;
	}
	
	public function city($v) {
		$ret = array();

		if (strlen($v) < 2)
			$ret[] = 'LEN_2';

		return $ret;
	}

	public function zip($v) {
		$ret = array();

		$v = preg_replace('/\s/', '', $v);

		if (!$v)
			$ret[] = 'NOTEMPTY';
		
		# No more validations for English form
		if ($this->lang === 'en')
			return $ret;

		if (preg_match('/\D/', $v))
			$ret[] = 'NUMONLY';

		return $ret;
	}

	public function country($v) {
		$ret = array();

		if (strlen($v) < 2)
			$ret[] = 'LEN_2';

		if (preg_match('/\d/', $v))
			$ret[] = 'NOTNUM';

		return $ret;
	}

	public function org_name($v) {
		if (strlen($v) < 2)
			return 'LEN_2';

		return true;
	}

	public function ic($v) {
		$v = preg_replace('/\s/', '', $v);
		
		if (strlen($v) < 6)
			return 'LEN_6';
		
		if (preg_match('/\D/', $v))
			return 'NUMONLY';

		return true;	
	}

	function distribution($v) {
		if (!$v)
			return 'NOTSELECTED';

		return true;
	}
	
	function location($v) {
		if (!$v)
			return 'NOTSELECTED';

		return true;
	}
	
	function currency($v) {
		if (!$v)
			return 'NOTSELECTED';

		return true;
	}
}
