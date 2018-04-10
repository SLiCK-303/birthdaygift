$(document).ready(function() {
	// Settings Form:
	$('#module_form').on('change', function() {

		var id_form = 'module_form';

		if (parseInt($("[name=BDAY_GIFT_VOUCHER]:checked").val()) === 0) {
			hideElement(id_form, 'BDAY_GIFT_PREFIX');
			hideElement(id_form, 'voucher_type1');
			hideElement(id_form, 'BDAY_GIFT_AMOUNT');
			hideElement(id_form, 'BDAY_GIFT_DAYS');
			hideElement(id_form, 'BDAY_GIFT_MINIMAL');
		} else {
			showElement(id_form, 'BDAY_GIFT_PREFIX');
			showElement(id_form, 'voucher_type1');
			showElement(id_form, 'BDAY_GIFT_AMOUNT');
			showElement(id_form, 'BDAY_GIFT_DAYS');
			showElement(id_form, 'BDAY_GIFT_MINIMAL');
		}

	}).trigger('change');

	function hideElement(form, id) {
		$('#'+form+' #'+id).closest('.form-wrapper > .form-group').hide();
	}

	function showElement(form, id) {
		$('#'+form+' #'+id).closest('.form-wrapper > .form-group').show();
	}

});
