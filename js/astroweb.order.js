/**
 * contains functions for uploading documents to astroweb and working with orders
 */
(function($){
	$(document).ready(function(){
		/** 
		 * Attachment upload start
		 */
		$("#_fel_invoice_attachment").on("change", function(){
			if ($("#_fel_invoice_attachment").val() == "") {
				$('#_fel_invoice_attachment_file').hide();
			}
			else {
				$('#_fel_invoice_attachment_file').show();
				$('#_fel_invoice_attachment_file').click(function(event) {
					event.stopPropagation();
	      		});
				$('#_fel_invoice_attachment_file').click();
			}
	    });
		$('#fel_invoice_upload_order_refresh').on('click', function() {
			location.href = location.href;
		});
		$('#_fel_invoice_attachment_file').on('change', prepareUpload);
		
		/**
		 * celebrity mail
		 */
		$('#_fel_invoice_celeb_active').on('change', function() {
			if ($('#_fel_invoice_celeb_active').prop('checked')) {
				$('#fel-invoice-celeb-div').show();
			}
			else {
				$('#fel-invoice-celeb-div').hide();
			}
		});
		//event handler for sending email notifications for celebrity deliveries
		$('#fel_invoice_send_celeb_mail').on('click', function() {
			if ($('#_fel_invoice_celeb').val() == "") {
				$('#_fel_invoice_celeb').focus().select();
			}
			else {
				var data = new FormData();
				data.append("action", "fel_invoice_celeb_mail");
				data.append("post_id", $('#fel_invoice_order_id').val());
				data.append("msg", $('#_fel_invoice_celeb').val());
				$('#fel-invoice-celeb-div').fadeOut(750);
				executeAjax(data, function(data, textStatus, jqXHR) {
					$('#fel-invoice-celeb-div').show();
					if(data.response == "SUCCESS"){
						$('#fel-invoice-celeb-div').addClass('fel-invoice-alert');
		        		$('#fel-invoice-celeb-div').addClass('fel-invoice-alert-success');
		        		$('#fel-invoice-celeb-div').html('ok!');
		        	}
					else {
						alert(data.error);
						$('#fel-invoice-celeb-div').show();
					}
				});
			}
		});
		//announce return
		$('#fel-invoice-announce-return').on('click', function(e) {
			if (confirm("Retoure anmelden?")) {
				$('#order_status').val('wc-return-announced');
			}
			else {
				e.preventDefault();
			}
		});
		$('#fel-invoice-reclamation-return').on('click', function(e) {
			if (confirm("Reklamation anmelden?")) {
				$('#order_status').val('wc-reclam-announced');
			}
			else {
				e.preventDefault();
			}
		});
		
		/**
		 * dispatcher loading
		 */
		//load available dispatcher profiles
		$('#fel-invoice-dispatcher-div').hide();
		var data = new FormData();
		data.append("action", "fel_invoice_load_dispatchers");
		data.append("post_id", $('#fel_invoice_order_id').val());
		var profiles = {};
		if ($('#fel_invoice_order_id') && $('#fel_invoice_order_id').val() > 0) {
			executeAjax(data, function(data, textStatus, jqXHR) {
				$('#fel-invoice-dispatcher-div').show();
				if(data.response == "SUCCESS") {
					$('#_fel_invoice_dispatcher').html('');
					$.each(data.items, function (i, item) {
					    $('#_fel_invoice_dispatcher').append($('<option>', {
					        value: item.value,
					        text : item.text 
					    }));
					});
					$('#_fel_invoice_dispatcher').val(data.selectedDispatcher);
					profiles = data.items;
					dispatcherSelected($('#_fel_invoice_dispatcher').val(), data.selectedDispatcherProduct);
				}
				else {
					// 2018-01-19, Patric Eid: don't show an error if profiles could not be loaded
					//alert(data.error);
				}
			});
		}
		$('#_fel_invoice_dispatcher').on('change', function() {
			dispatcherSelected($('#_fel_invoice_dispatcher').val(), "");
		});
		/**
		 * called when a dispatcher was selected and adds dispatcher products to second select box
		 */
		function dispatcherSelected(selectedId, selectedProfile) {
			var result = $.grep(profiles, function(e){ return e.value == selectedId; });
			$('#_fel_invoice_dispatcher_product').html('');
			if (result.length > 0) {
				$.each(result, function (i, item) {
					$.each(item.subarray, function (j, sub) {
						$('#_fel_invoice_dispatcher_product').append($('<option>', {
					        value: sub.value,
					        text : sub.text 
						}));
					});
				});
			}
			$('#_fel_invoice_dispatcher_product').val(selectedProfile);
		}
		
		/**
		 * executes an ajax call
		 */
		function executeAjax(data, cb) {
			$.ajax({
				url: astrowebOrder.ajax_url,
		        type: 'POST',
		        data: data,
		        cache: false,
		        dataType: 'json',
		        processData: false, // Don't process the files
		        contentType: false, // Set content type to false as jQuery will tell the server its a query string request
		        success: function(data, textStatus, jqXHR) {
		          cb(data, textStatus, jqXHR);
		        }
			});
		}
		
		/**
		 * prepares file upload and executes ajax call
		 */
		function prepareUpload(event) { 
			$('#fel_invoice_upload_message').html('uploading...');
			$('#fel_invoice_upload_message').removeClass('fel-invoice-alert-danger');
			$('#fel_invoice_upload_message').removeClass('fel-invoice-alert-success');
			$('#fel_invoice_upload_message').show();
			$('#fel_invoice_upload_order_refresh').hide();
			var file = event.target.files;
			var data = new FormData();
			data.append("action", "fel_invoice_file_upload");
			data.append("post_id", $('#fel_invoice_order_id').val());
			data.append("fel_invoice_attachment_type", $('#_fel_invoice_attachment').val());
			$.each(file, function(key, value)
  			{
    			data.append("fel_invoice_attachment_file", value);
  			});
			executeAjax(data, function(data, textStatus, jqXHR) {
				if(data.response == "SUCCESS"){
	        		  $('#fel_invoice_upload_message').html('ok!').delay(6000).fadeOut();
	        		  $('#fel_invoice_upload_order_refresh').show();
	        		  $('#_fel_invoice_attachment_file').hide();
	        		  $('#fel_invoice_upload_message').addClass('fel-invoice-alert-success');
	        	  }
	        	  else {
	        		  $('#fel_invoice_upload_message').html(data.error);
	        		  $('#fel_invoice_upload_message').addClass('fel-invoice-alert-danger');
	        	  }
			});
		}
	});
})(jQuery);