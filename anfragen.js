jQuery(document).ready(
	function($) {
		$('#anfragen_response_date_field').datepicker({
			dateFormat			: 'dd-mm-yy',
			showOtherMonths		: true,
			selectOtherMonths	: true
		});
	}
);