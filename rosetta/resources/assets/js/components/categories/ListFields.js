export default[
	{
		name:"code",
		title:"Code"
	},
	{
		name:"display_name",
		title:"Display Name"
	},
	{
		name:"description",
		title:"Description",
	},
	{
		name:"allow_cl",
		title:"Allow CL",
		callback:function(val){
			return val ? '<i class="fa fa-check"></i>' : ''
		},
		dataClass:'shrink text-center',
		titleClass:'shrink'
	},
	{
    	name: '__slot:cl_percent',
    	title: 'CL percent',
    	dataClass:'shrink text-center',
    	titleClass:'shrink'
  	},
	{
		name:"in_multiples",
		title:"In Multiples",
		callback:function(val){
			return val ? val : ''
		},
		dataClass:'shrink text-center',
    	titleClass:'shrink'
	},
	{
		name:"plank_size",
		title:"Plank Size",
		callback:function(val){
			return val ? val : ''
		},
		dataClass:'shrink text-center',
    	titleClass:'shrink'
	},
/*	{
		name:"general_product_posting_group",
		title:"General_Product_Posting_Group",
	},
	{
		name:"inventory_posting_group",
		title:"Inventory_Posting_Group",
	},
	{
		name:"tax_group_code",
		title:"Tax_Group_Code",
	},
	{
		name:"costing_method",
		title:"Costing_Method",
	},
	{
		name:"vat_product_posting_group",
		title:"VAT_Product_Posting_Group",
	},*/
	{
    	name: '__slot:actions',
    	title: '',
    	titleClass: 'text-right has-actions',
    	dataClass: 'text-right has-actions'
  	}
]