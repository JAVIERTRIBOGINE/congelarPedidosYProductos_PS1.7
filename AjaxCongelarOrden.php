<?php

    /**
     *  DOC PARA MODIFICAR VALORES EN BBDD PARA QUE EL PEDIDO SELECCIONADO
     *  APUNTE A OTRA MATRÍCULA
     *  SE TRATA DE HACER UN UPTDATE EN LA TABLA CREADA ps_matricula_order,
     *  donde se relacinan pedidos con matrículas
     */
    require_once dirname(__FILE__).'../../config/config.inc.php';
    require_once dirname(__FILE__).'../../init.php';

    // -------------------- RECOGEMOS VARIABLES ----------------------------
    //Variables recogidas de los campos de la pagina de pedido del backoffice
    $idOrderLargo=Tools::getValue('idOrder');
    $idCustomerLargo=Tools::getValue('idCustomer');
    $idOrderDetail=Tools::getValue('idOrderDetail');
    $auxOrder=explode('-', $idOrderLargo);
    $auxCustomer=explode('-', $idCustomerLargo);
    $idOrderCorto=$auxOrder[1];
    $idCustomerCorto=$auxCustomer[1];
    //Creación de objetos necesarios para posteriores operaciones lógicas
    $idSemestreActivo=Semestre::getIdActive();
    $semestreActual= new Semestre($idSemestreActivo);
    $idShop=configuration::get('PS_SHOP_DEFAULT');
    //objeto carrito
    $cart = new Cart (Cart::getCartByOrderId($idOrderCorto));
    $idLang = $cart->id_lang;

    //se carga y modifica el pedido: pasa a estado 'congelado' (creado por backoffice)
    $order = new Order($idOrderCorto);
    $order->valid=0;
    $order->current_state=14;
    $order->update();

    $matriculaActual=new MatriculaObject(MatriculaObject::getIdMatriculaFromIdOrder($idOrderCorto));

    // llamada a estático donde se evalúa si existe un semestre siguiente
    $existeSiguienteSemestre=Semestre::existNextSemestres($semestreActual);

    // si no existe devuelve una evaluacion 'no semestres'
    if(!$existeSiguienteSemestre){
      die(json_encode(
        array(
        'evaluacion' => 'no semestres',
        'value' => $semestreActual->id,
        'ped' => $order,
        'mat' => $matriculaActual
        )));

        // si existe semestre siguiente, se continua
    }else{
        $idSemestreSiguiente=Semestre::findIdNextSemestre($semestreActual);
        $customer= new Customer($idCustomerCorto);

        /*  se evalua si existe una matricula creada por ese alumno
        *   en un semestre posterior (debido a una congelación también)
        */
        if(!($idNextMatricula=MatriculaObject::existIdNextMatricula($idSemestreSiguiente, $idCustomerCorto)))
        {
          // Se crea una matricula, vinculada a un semestre
          $matriculaNueva=new MatriculaObject();
          $matriculaNueva->id_customer=$idCustomerCorto;
          $matriculaNueva->id_semestre=$idSemestreSiguiente;
          $matriculaNueva->active=0;
          $matriculaNueva->add();
          $idNextMatricula = $matriculaNueva->id;
          $matriculaOrderNuevo=new MatriculaOrder(MatriculaOrder::getIdMatriculaOrder($matriculaActual->id, $idOrderCorto));
          $matriculaOrderNuevo->id_matricula=$matriculaNueva->id;
          $matriculaOrderNuevo->id_order=$order->id;
          $matriculaOrderNuevo->active=0;
          $matriculaOrderNuevo->update();
          die(json_encode(
            array(
              'evaluacion' => 'ok',
              'value' => $semestreActual->id,
              'sig' => $idSemestreSiguiente,
              'actual' => $semestreActual->id,
              'mat' => $matriculaActual,
              'idNextMat' => $idNextMatricula,
              'idOrder' => $idOrderCorto,
              'idCustomer' =>$idCustomerCorto,
              'matriculaNueva' => $matriculaNueva,
              'pedidoMatriculaNuevo' => $matriculaOrderNuevo
          )));
        }else{

        /*  se crea un objeto-espejo matriculaOrder de base de datos y
        *   modifico el valor del id de la matricula vinculada
        */
        $matriculaOrder=new MatriculaOrder(MatriculaOrder::getIdMatriculaOrder($matriculaActual->id,$idOrderCorto));
        $matriculaOrder->id_order=$order->id;
        $matriculaOrder->id_matricula=$idNextMatricula;
        $matriculaOrder->active=0;
        $matriculaOrder->update();
        }

        $mensaje=Order::addFreezeOrderMessage($idOrderCorto, $matriculaActual->id, $idNextMatricula, $customer);

     // CREO Y VINCULO CUSTOMERTHREAD Y CustomerMessage. Estos objetos-espejo
     // recogen de la base de datos la información del cliente con sus pedidos

         $CustomerThread= new CustomerThread();
         $CustomerThread->id_order=$order->id;
         $CustomerThread->id_customer = $idCustomerCorto;
         $CustomerThread->id_shop = $idShop;
         $CustomerThread->id_order = $idOrderCorto;
         $CustomerThread->id_contact = $idShop;
         $CustomerThread->id_lang = $idLang;
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
            'value' => $semestreActual->id,
            'sig' => $idSemestreSiguiente,
            'actual' => $semestreActual->id,
            'mat' => $matriculaActual->id,
            'idNextMat' => $idNextMatricula,
            'idOrder' => $idOrderCorto,
            'idCustomer' =>$idCustomerCorto,
            'mensaje' => $mensaje
        )));
    }


?>
