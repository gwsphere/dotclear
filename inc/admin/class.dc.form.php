<?php


class dcFormNode extends Twig_Node {
    public function __construct($name, Twig_NodeInterface $body, $lineno, $tag = null)
    {
        parent::__construct(array('body' => $body), array('name' => $name), $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write("\$context['dc_form']->beginForm('".
            	 $this->getAttribute('name')."');\n")
            ->subcompile($this->getNode('body'))
            ->write("\$context['dc_form']->endForm();\n")
        ;
    }

}

class dcFormTokenParser extends Twig_TokenParser {

	public function parse(Twig_Token $token) {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $name = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
        $stream->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
        $stream->expect(Twig_Token::BLOCK_END_TYPE);
        return new dcFormNode ($name, $body, $token->getLine(), $this->getTag());
	}

	public function decideBlockEnd(Twig_Token $token)
    {
        return $token->test('endform');
    }


    public function getTag()
    {
        return 'form';
    }

}

class dcFormExtension extends Twig_Extension {
	protected $template;
	protected $tpl;
	protected $core;
	protected $twig;
	protected $blocks;
	protected $forms;
	protected $currentForm;

	public function __construct($core){
		$this->core = $core;
		$this->tpl = 'form_layout.html.twig';
		$this->forms = array();
		$this->currentForm = null;
	}

    public function initRuntime(Twig_Environment $environment)
    {
    	$this->twig = $environment;
		$this->template = $this->twig->loadTemplate($this->tpl);
    	$this->blocks = $this->template->getBlocks();
    }

    public function getGlobals() {
    	return array('dc_form' => $this);
    }

	public function getFunctions() {
		return array(
			'form_field' => new Twig_Function_Method($this, 'renderWidget', array('is_safe' => array('html'))),
            '_form_is_choice_group'    => new \Twig_Function_Method($this, 'isChoiceGroup', array('is_safe' => array('html'))),
            '_form_is_choice_selected' => new \Twig_Function_Method($this, 'isChoiceSelected', array('is_safe' => array('html')))

		);
	}

	public function isChoiceGroup($choice) {
		return is_array($choice);
	}
	public function isChoiceSelected($choice,$value) {
		return $choice == $value;

	}

	public function getTokenParsers() {
		return array(new dcFormTokenParser());
	}

	public function renderWidget($name,$attributes=array()) {
		$field = $this->currentForm->$name;
		if ($field)
		echo $this->template->renderBlock(
			$field->getWidgetBlock(),
			array_merge($field->getAttributes(),array('attr'=>$attributes)),
			$this->blocks);

	}

	public function getName() {
		return 'dc_form';
	}

	public function addForm(dcForm $form) {
		$this->forms[$form->getName()]=$form;
	}

	public function beginForm($name) {
		if (isset($this->forms[$name])) {
			$this->currentForm = $this->forms[$name];
			$this->currentForm->begin();
		} else {
			throw new Twig_Error_Runtime("Form '".$name."' does not exist");
		}
	}
	public function endForm() {
		$this->currentForm->end();
		$this->currentForm = null;
	}

}


class dcForm {
	protected $name;
	protected $core;
	protected $action;
	protected $fields;

	public function __construct($core,$name,$action, $method='POST') {
		$this->core = $core;
		$this->name = $name;
		$this->method = $method;
		$this->action = $action;
		$this->fields = array();
		$this->core->page->getExtension('dc_form')->addForm($this);
	}

	public function getName() {
		return $this->name;
	}

	public function addField(dcField $f) {
		$this->fields[$f->getName()]=$f;
		return $this;
	}

	public function begin() {
		echo '<form method="'.$this->method.'" action="'.$this->action.'">';
	}

	public function end() {
		echo '</form>';
	}


    public function __isset($name) {
		return isset($this->fields[$name]);
    }

    public function __get($name) {
		if (isset($this->fields[$name])) {
			return $this->fields[$name];
		} else {
			return null;
		}
    }

    public function __set($name,$value) {
		if (isset($this->fields[$name])) {
			$this->fields[$name]->setAttribute('value',$value);
		}
    }

}

abstract class dcField {
	protected $attributes;
	protected $name;
	protected $value;
	protected $id;
	
	protected function getNID($nid) {
		if (is_array($nid)) {
			$this->name = $nid[0];
			$this->id = !empty($nid[1]) ? $nid[1] : null;
		} else {
			$this->id = $this->name = $nid;
		}
	}

	public function __construct($name, $value, $attributes = array()) {
		$this->getNID($name);
		$this->attributes = $attributes;
		$this->value = $value;
		$this->attributes['name'] = $this->name;
		$this->attributes['id'] = $this->id;
		$this->attributes['value'] = $this->value;

	}

	abstract public function getWidgetBlock();

	public function setAttribute ($name,$value) {
		$this->attributes[$name] = $value;
	}

	public function getAttributes() {
		return $this->attributes;

	}

	public function getName(){
		return $this->name;
	}

	public function check() {

	}
}


class dcFieldText extends dcField {

	public function getWidgetBlock() {
		return "field_text";
	}

}

class dcFieldTextArea extends dcField {

	public function getWidgetBlock() {
		return "field_textarea";
	}

}

class dcFieldHidden extends dcField {

	public function getWidgetBlock() {
		return "field_hidden";
	}

}

class dcFieldCheckbox extends dcField {

	public function getWidgetBlock() {
		return "field_checkbox";
	}

}

class dcFieldSubmit extends dcField {

	public function getWidgetBlock() {
		return "field_submit";
	}

}

class dcFieldCombo extends dcField {
	protected $options;

	public function __construct($name, $value, $options, $attributes = array()) {
		$this->options = $options;
		parent::__construct($name,$value,$attributes);
		$this->attributes['options']=$options;
	}

	public function getWidgetBlock() {
		return "field_combo";
	}

}

?>