<?php

	require_once dirname(__FILE__).'../../config/config.inc.php';
	require_once dirname(__FILE__).'../../init.php';

	/**
	 *	PAYMENTMODULE es el objeto que, tras pulsar boton de crear pedido, se
	 *	encarga de registrar carrito, histórico de estados, aplicación de
	 * 	descuentos, asignación de transportista, llamada a almacén, mail con factura...
	 *	Es un objeto fundaental
	 */

	class BoOrder extends PaymentModule
	{
	    public $active = 1;
	    public $name = 'bo_order';

	    public function __construct()
	    {
	        $this->displayName = $this->trans('Back office order', array(), 'Admin.Orderscustomers.Feature');
	    }
	}

  // RECOJO LAS VARIABLES de los campos de la pagina de pedido del backoffice
	$idOrderLargo=Tools::getValue('idOrder');
	$idCustomerLargo=Tools::getValue('idCustomer');
	$auxOrder=explode('-', $idOrderLargo);
	$auxCustomer=explode('-', $idCustomerLargo);
	$idSemestreActivo=Semestre::getIdActive();
	$semestreActual= new Semestre($idSemestreActivo);
	$idOrderCorto=$auxOrder[1];
	$idCustomerCorto=$auxCustomer[1];
	$idProduct=Tools::getValue('idProduct');
  $idOrderDetail = Tools::getValue('idOrderDetail');
	$quantity = (int)Tools::getValue('quantity');
	$price = Tools::getValue('priceProduct');
	$amount = Tools::ps_round((float)$quantity,2)*$price;
	$idShop=configuration::get('PS_SHOP_DEFAULT');

  //	MIRO SI SOLO HAY UN PRODUCTO EN EL pedido. Si es asi, notifico que se
	//	haga una congelación de pedido y no de producto.

  if(OrderDetail::getNumberOfProducts($idOrderCorto)==1){
    die(json_encode(
			array(
				'evaluacion' => 'one_product',
				'order' => $idOrderCorto
			)));

			// evalúo si se ha registrado un semestre siguiente. Si no hay, se notifica
  }elseif (!(Semestre::existNextSemestres($semestreActual))) {
		die(json_encode(
			array(
				'evaluacion' => 'no semestres',
				'value' => $semestreActual->id,
				'ped' => $idOrderCorto,
				'mat' => $matriculaActual
			)));
  }else
	// SI HAY SIGUIENTE SEMESTRE
	{
			// CREO OBJETO SEMESTRE ACTIVO Y EL ID DEL SIGUIENTE SEMESTRE
			$idSemestreActivo=Semestre::getIdActive();
			$semestreActual= new Semestre($idSemestreActivo);

			//COJO EL ID DEL SIGUIENTE SEMESTRE
			$idSemestreSiguiente=Semestre::findIdNextSemestre($semestreActual);

			 // CREO OBJETO MATRICULA ACTUAL
			$matriculaActual=new MatriculaObject(MatriculaObject::getIdMatriculaFromIdOrder($idOrderCorto));

			// EVALUO SI HAY UNA MATRICULA POSTERIOR A LA ACTUAL
			if(!($idNextMatricula=MatriculaObject::existIdNextMatricula($idSemestreSiguiente, $idCustomerCorto)))
			{
				  // SI NO HAY MATRICULA EN SIGUIENTE SEMESTRE ... LA CREO
					$matriculaSiguiente=new MatriculaObject();
					$matriculaSiguiente->id_customer=$idCustomerCorto;
					$matriculaSiguiente->id_semestre=$idSemestreSiguiente;
					$matriculaSiguiente->active=0;
					$matriculaSiguiente->add();
					$idNextMatricula = $matriculaSiguiente->id;

				// PERO SI EXISTE, CARGO EL OBJETO
			}else{
					$matriculaSiguiente = new MatriculaObject($idNextMatricula);
					$matriculaSiguiente->id_customer=$idCustomerCorto;
					$matriculaSiguiente->id_semestre=$idSemestreSiguiente;
					$matriculaSiguiente->active=0;
			}
					//CREO OBJETO SEMESTER SIGUIENTE. LO NECESITO PARA LOS MENSAJES
					$semestreSiguiente = new Semestre($idSemestreSiguiente);
					// CARGO CUSTOMER DEL PEDIDO
					$address = new Address((int)Address::getFirstCustomerAddressId($idCustomerCorto));
					$customer=new Customer($idCustomerCorto);
					// $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT');
					$reference = Order::generateReference();
					$orderActual = new Order($idOrderCorto);

					// CREO UN CARRITO PARA EL PEDIDO QUE VOY A CREAR (EN EL QUE TRASLADARÉ EL PRODUCTO CONGELADO)
					$carritoNuevo = new Cart();
					$carritoActual = Cart::getCartByOrderId($orderActual->id);

					//CREACIÓN DEL CARRITO A PARTIR DEL CARRITO INICIAL
					$carritoNuevo->id_currency = $carritoActual->id_currency;
					$carritoNuevo->id_lang = $carritoActual->id_lang;
					$carritoNuevo->id_customer = $carritoActual->id_customer;
					$carritoNuevo->secure_key = false;
					$carritoNuevo->id_guest = $carritoActual->id_guest;
					$carritoNuevo->id_shop_group = $carritoActual->id_shop_group;
					$carritoNuevo->id_carrier = $carritoActual->id_carrier;
					$carritoNuevo->date_add = $carritoActual->date_add;
					$carritoNuevo->date_upd = $carritoActual->date_upd;
					$carritoNuevo->id_address_invoice = $carritoActual->id_address_invoice;
					$carritoNuevo->id_address_delivery = $carritoActual->id_address_delivery;
					$carritoNuevo->delivery_option=$carritoActual->delivery_option;

					$producto = array (
						0 => array (
							'id_product' => $idProduct,
							'quantity' => $quantity,
							'id_product_attribute' => 0 //no era null, era 0. Ahí estaba el fallo
							)
						);

					$carritoNuevo->add();
					$carritoNuevo->setWsCartRows($producto);
					$module_name = Tools::getValue('Pagos por transferencia bancaria');
					$payment_module = new BoOrder();
					$extra_vars = array();

					$payment_module->validateOrder($carritoNuevo->id, '14', $amount, 'Pagos por transferencia bancaria','hola',$extra_vars,null, false, $carritoNuevo->secure_key, $context->shop);
					$orderInicial = new Order($idOrderCorto);
					$orderNueva= $payment_module->currentOrder;
					$idMatriculaOrder = (int)MatriculaOrder::getIdMatriculaOrder ($matriculaActual->id, $orderNueva);

					/*problema resuelto por Jordi Freixa: en el modulo matricula_consulta...
					... ya se crea un matricula_order (esta en el hook de validacion de
					matricula). Se trata de hacer update de este registro recien creado,
					no un add (porque se duplican)--linea 143--*/

					$matriculaOrderNuevo=new MatriculaOrder($idMatriculaOrder);
					$matriculaOrderNuevo->id_matricula=(int)$idNextMatricula;
					$matriculaOrderNuevo->active=0;
					$matriculaOrderNuevo->update();

					$orderDetail = new OrderDetail ($idOrderDetail);
					$orderDetail->id_order=$orderNueva;
					$orderDetail->update();
					$mensaje=Order::addFreezeProductOrdersMessage($idOrderCorto, $orderNueva, $matriculaActual->id, $matriculaSiguiente->id, $semestreActual->key, $semestreSiguiente->key, $customer);

					// AÑADIREMOS MENSAJE ASOCIADO A PEDIDO NUEVO Y CUSTOMER_THREAD
					$CustomerThread= new CustomerThread();
					$CustomerThread->id_order=(int)$orderNueva;
					$CustomerThread->id_customer = $customer->id;
					$CustomerThread->id_shop = $idShop;
					$CustomerThread->id_order = $orderNueva;
					$CustomerThread->id_contact = $idShop;
					$CustomerThread->id_lang = $carritoActual->id_lang;
					$CustomerThread->email = $customer->email;
					$CustomerThread->status = 'open';
					$CustomerThread->token = Tools::passwdGen(12);
					$CustomerThread->add();


					$CustomerMessage = new CustomerMessage();
					$CustomerMessage->id_customer_thread = $CustomerThread->id;
					$CustomerMessage->id_employee = 2;
					$CustomerMessage->message = $mensaje;
					$CustomerMessage->date_add = date("Y-m-d H:i:s");
					$CustomerMessage->date_upd = date("Y-m-d H:i:s");
					$CustomerMessage->private = 1;
					$CustomerMessage->read =0;
					$CustomerMessage-> add();

					die(json_encode(
						array(
							'evaluacion' => 'ok',
							'pagoTotal' => $amount,
							'cantidad' => $quantity,
							'precio' => $price,
							'secure_key' => $carritoNuevo->secure_key,
							'idCarroCreado' => $carritoNuevo->id,
							'idPedidoNuevo' => $orderNueva,
							'idOrderAnterior' => $idOrderCorto,
							'idCustomer' => $customer->id,
							'semestreActual' => $semestreActual->id,
							'idSemestreSiguiente' => $idSemestreSiguiente,
							'idMatriculaActual' => $matriculaActual->id,
							'idMatriculaSiguiente' => $idNextMatricula,
							'idProducto' => $idProduct,
							'idCarroCreado' => $carritoNuevo->id,
							'mensaje' => $mensaje

						)));

  }


?>
