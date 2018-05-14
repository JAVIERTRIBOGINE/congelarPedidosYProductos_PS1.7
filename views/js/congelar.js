/**
 *  Envia por AJAX las llamadas a bbDD y devuelve resultados a modo de notifi-
 *  cación sobre lo resultados obtenidos
 */

$(document).ready(function(){


  //  AGRUPO LOS PRODUCTOS QUE NO SE PUEDEN CONGELAR Y EVALUO SI ES UN PEDIDO
  //  DE ÚNICO PRODUCTO Y SI EL PRODUCTO DEL PEDIDO COINCIDE CON LA AGRUPACIÓN
  var exceptionOrder = [923, 382, 653, 452];
  if (($('.freezeProduct').length==1) &&  exceptionOrder.indexOf(infoProd[1])!=-1){
      $('.freezeProduct').Attr('disabled');
  }else {
    var id_order = $('.idPedido').attr('id');
    var id_customer = $('.idCustomerr').attr('id');

    /**
     * ***************** CONGELACIÓN DE PRODUCTO *************************
     */


    // HABILITO EL BOTON DE CONGELAR PEDIDO

    $('#desc-order-freezeOrder').removeAttr('disabled').click(function() {

    // CONFIRMACIÓN DE QUE SE QUIERE CONGELAR PEDIDO
      if(!confirm('quieres congelar este pedido?'))
      return false;

      // A J A X
      $.ajax(
          {
            type: 'POST',
            url: baseDir + 'modules/congelar/AjaxCongelarOrden.php',
            cache: false,
            dataType: 'json',
            data : {
            ajax : true,
            idOrder : id_order,
            idCustomer : id_customer
            },
            success : function(datos){

              if(datos['evaluacion']=='no semestres') {
              // SI NO EXISTE UN SEMESTRE POSTERIOR', DEVUELVE NOTIFICACIÓN
                  $('#no_semestre').attr('display','block').html("Debes crear un semestres posterior no activo").fadeIn(500).fadeOut(5000).attr('display','none');
                  console.log("matricula id: " + datos['mat']['id']);
                  console.log("orden id : "+ datos['ped']['id']);
                  console.log("id semestres actual: "+datos['value']);
                  console.log("hay ultimo semestres? "+datos['maxSem']);

              // SI SE EVALUA COMO OK, DEVUELVE NOTIFICACIÓN
              }
              else if(datos['evaluacion']=='ok') {

                  $('#congelacion_ok').attr('display','block').html("Se ha congelado el pedido. Se ha generado anotación").fadeIn(500).fadeOut(5000).attr('display','none');
                  $('#txt_msg').val(datos['mensaje']);
                  $('#submitMessage').click();
              }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown)
              {
                jAlert("TECHNICAL ERROR: \n\nDetails:\nError thrown: " + XMLHttpRequest + "\n" + 'Text status: ' + textStatus);
                console.log("error");
              }
          }
        );
    });

    /**
     * ***************** CONGELACIÓN DE PRODUCTO *************************
     */

     // HABILITO EL BOTON DE CONGELAR

    $('.freezeProduct').removeAttr('disabled').click(function()
    {
        var infoProd=$(this).attr('id').split('-');
        var tr_product = $(this).closest('.product-line-row');
        var id_order_detail=infoProd[0];
        var id_product=infoProd[1];
        var quantity=infoProd[2];
        var price=infoProd[3];
        var mensaje = $('#txt_msg').text()
        var payment_module = $("#payment_module_name").val();
        console.log("mensaje: "+mensaje);
        console.log("id product: "+id_product);
        console.log("id order detail: "+id_order_detail);
        console.log("quantity: "+quantity);
        console.log("price: "+price);
        if(!confirm('quieres congelar este producto?'))
        return false;
        $.ajax({
            type: 'POST',
            cache: false,
            dataType: 'json',
            url : baseDir+'modules/congelar/AjaxCongelarProducto.php',
            data : {
            idProduct : id_product,
            idOrder : id_order,
            idOrderDetail : id_order_detail,
            quantity : quantity,
            priceProduct : price,
            idCustomer : id_customer
            },
            success : function(datos)
            {

              if(datos['evaluacion']=='no semestres'){
                $('#no_semestre').attr('display','block').html("Debes crear un semestres posterior no activo").fadeIn(500).fadeOut(5000).attr('display','none');
              }
              else if(datos['evaluacion'] == 'one_product')
              {
                $('#no_semestre').attr('display','block').html("pedido con id='"+datos['ped']+"' solo tiene 1 producto.Para pedidos de un producto, mejor usa el boton de congelar pedido").fadeIn(500).fadeOut(5000).attr('display','none');

              }else if(datos['evaluacion']=='ok'){

                tr_product.fadeOut('slow', function() {
                  $(this).remove();
                });
              $('#txt_msg').val(datos['mensaje']);
              $('#submitMessage').click();
            }},
            error: function(XMLHttpRequest, textStatus, errorThrown) {
              console.log(textStatus);
              console.log(errorThrown);
              jAlert("TECHNICAL ERROR: \n\nDetails:\nError thrown: " + XMLHttpRequest + "\n" + 'Text status: ' + textStatus);
            }
        });
      });
    }
});
