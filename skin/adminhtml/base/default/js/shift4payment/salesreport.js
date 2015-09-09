jQuery.noConflict();
var validator = new Validation('reportForm');

jQuery(document).ready(function(){
	Calendar.setup({
		inputField: 'report_from',
		ifFormat: '%m/%d/%Y',
		button: 'report_from',
		align: 'Bl',
		singleClick: true,
		weekNumbers: false
	});
	Calendar.setup({
		inputField: 'report_to',
		ifFormat: '%m/%d/%Y',
		button: 'report_to',
		align: 'Bl',
		singleClick: true,
		weekNumbers: false
	});

	jQuery.jgrid.defaults = {
		datatype: 'jsonstring',
		jsonReader: {
			root: 'rows',
			repeatitems: false
		},
		height: 'auto',
		rowNum: 9999,
		sortorder: 'desc'
	}
	jQuery("#salesGrid").jqGrid({
		datastr: salesGridData,
		colNames: ['Invoice #', 'Date','Card Type','Card Number', 'Amount', 'Total', 'Customer Name'],
		colModel: [
			{name:'invoice',		width: 100},
			{name:'datetime',		width: 150},
			{name:'cc_type',		width: 150},
			{name:'cc_last4',		width: 100},
			{name:'amount',			width: 100, align: 'right', sorttype: 'currency'},
			{name:'total',			width: 100, align: 'right', sorttype: 'currency'},
			{name:'customer_name',	width: 150}
		]
	});
	jQuery("#statsGrid").jqGrid({
		datastr: statsGridData,
		colNames: ['Card Type', 'Count', 'Total'],
		colModel: [
			{name:'cc_type',		width: 150},
			{name:'count',			width: 100, align: 'right'},
			{name:'total',			width: 100, align: 'right', sorttype: 'currency'}
		]
	});	
	jQuery("#timeoutsGrid").jqGrid({
		datastr: timeoutsGridData,
		colNames: ['Invoice #', 'Date', 'Card Type', 'Card Number', 'Amount', 'Customer Name', 'Customer Id'],
		colModel: [
			{name:'invoice',		width: 100},
			{name:'datetime',		width: 150},
			{name:'cc_type',		width: 150},
			{name:'cc_last4',		width: 100},
			{name:'amount',			width: 100, align: 'right', sorttype: 'currency'},
			{name:'customer_name',	width: 150},
			{name:'customer_id',	width: 100}
		]
	});
});