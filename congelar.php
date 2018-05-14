	<?php
/**
 *	Se optó por implementar la funcionalidad de congelar pedidos y productos
 *	para dar opción a habilitar deshabilitar según si se instala o no el módulo
 *	LA PARTE LÓGICA DEL MÓDULO, SE SE LLAMA POR AJAX, SE ENCUENTRA EN:
 *
 *		- ajaxcongelarOrden.php (para congelación de pedidos)
 *		- ajaxcongelarProducto.php (para la congelación de productos)
 */

if (!defined('_PS_VERSION_'))
exit;

class congelar extends Module {

	public function __construct() {
	$this->name = 'congelar';
	$this->tab = 'back_office_features';
	$this->version = '1.0.0';
	$this->author = 'IT';
	$this->need_instance = 0;
	$this->ps_versions_compliancy = array('min'=>'1.6','max'=>_PS_VERSION_);
	$this->bootstrap = true;
	parent::__construct();
	$this->displayName = $this->l('congelar');
	$this->description = $this->l('Modulo congelar pedidos y módulos');
	$this->confirmUninstall = $this->l('¿Desea desinstalar?');
	}
	/**
	 * En la instalación, se registra al hook que recoge el javascript
	 */
	public function install() {
		if (!parent::install() ||!$this->registerHook('ActionAdminControllerSetMedia'))
			return false;
		return true;
	}

	public function uninstall() {
		if (!parent::uninstall() || !$this->unregisterHook('ActionAdminControllerSetMedia'))
			return false;
		return true;
	}

	/**
	 * metodo que vincula el javacript del modulo al proyecto
	 */
	public function hookActionAdminControllerSetMedia()
    {
    	$this->context->controller->addJs($this->_path.'views/js/'.$this->name.'.js');
    }

	}
