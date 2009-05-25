<?php

/**
 * A field containing the login form for google authentication
 */
class LoginField extends FormField {
	protected $username;
	protected $passwd;
	protected $action;
	protected $error;
	protected $table;
	
	public function __construct($name,$action,$error=null, $value=null,$form=null) {
		if($value!=null)
		{
			list($this->username,$this->passwd) = unserialize($value);
		}
		if($action!=null)
		{
			$this->action = $action;
		}
		if($error!=null)
		{
			$this->error = $error;
		}
		
		Requirements::javascript("jsparty/loader.js");
		Requirements::javascript("jsparty/prototype.js");
		Requirements::javascript("jsparty/behaviour.js");
		Requirements::javascript("jsparty/prototype_improvements.js");
		Requirements::CustomScript("
		Behaviour.register({
			'.text' : {
				onfocus: function() {
					this.setAttribute('maxlength',128);
				},
				onload: function() {
					this.setAttribute('maxlength',128);
				}
			}
		});","LoginExtension");
		
		parent::__construct($name,"",$value,$form);
		
		$table = array(
		'Top'=>
"<table border=\"0\" cellpadding=\"5\" cellspacing=\"3\">
	<tbody><tr>
	<td style=\"text-align: center;\" bgcolor=\"#e8eefa\" nowrap=\"nowrap\" valign=\"top\">
		<table align=\"center\" border=\"0\" cellpadding=\"1\" cellspacing=\"0\">
			<tbody><tr>
			<td colspan=\"2\" align=\"center\">
			<span style=\"\">". _t('LoginField.SIGNINWITH','Sign in with your') . "</span>
			<table><tbody><tr><td valign=\"top\"><img src=\"googleadwords/images/google_transparent.gif\" alt=\"Google\"></td>
					   <td valign=\"middle\"><span style=\"font-size: medium; font-family: sans-serif;\"><b>" . _t('LoginField.ACCOUNT','Account') . "</b></span>
					</td></tr></tbody></table>
			</td>
			</tr>
			<tr>
			<td colspan=\"2\" align=\"center\">
			</td>
			</tr>
			<tr>
			<td nowrap=\"nowrap\">
			<div align=\"right\">
			" . _t('LoginField.EMAIL','Email:') . "
			</div>
			</td>
			<td>", 'M1'=>'
</td>
</tr>
<tr><td></td>
<td align="left">
</td>
</tr>
<tr>
<td align="right">
' . _t('LoginField.PASSWORD','Password:') .'
</td><td>', 

			'M2'=>'</td></tr>
<tr><td></td><td align="left"><div style="color: red; font-size: smaller; font-family:arial,sans-serif;" id="errormsg">',

			'M3'=>'</div></td></tr>
<tr><td></td><td align="left"></td></tr>
<tr><td>
</td>
<td align="left">', 
			'Bottom'=>'
			</td>
			</tr>
			</tbody></table>
	</td>
	</tr>
</tbody></table>');
	}
	
	public function Field() {
		$field = new FieldGroup($this->name);
		
		$field->push(new LiteralField($this->name.'[Styling1]',$this->table['Top']));
		$field->push(new TextField($this->name.'[Username]',"",$this->username,18));
		$field->push(new LiteralField($this->name.'[Styling2]',$this->table['M1']));
		$field->push(new PasswordField($this->name.'[Passwd]',"",$this->passwd,18));
		$field->push(new LiteralField($this->name.'[Styling3]',$this->table['M2']));
		if(!is_null($this->error))
		{
			$field->push(new LiteralField($this->name.'[Error]',$this->error));
		}
		$field->push(new LiteralField($this->name.'[Styling4]',$this->table['M3']));
		$field->push(new FormAction_WithoutLabel($this->action, _t('LoginField.SIGNIN','Sign in')));
		$field->push(new LiteralField($this->name.'[Styling5]',$this->table['Bottom']));
		
		return $field;
	}
	
	public function setValue($value)
	{
		if(is_array($value))
		{
			$this->value=serialize($value);
		}
	}
	

	
}

?>