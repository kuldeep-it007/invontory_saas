export default[
	{
		name:"si_numbers",
		title:"SI Number",
		callback:function(val){
			return val.join(' ')
		}
	},
	{
		name:"ros_invoice_number",
		title:"PI Number",
	},
	{
		name:"so_numbers",
		title:"SO No.",
		callback:function(val){
			return val.join(' ')
		}
	},
	{
		name:"Dealer_po_number",
		title:"Customer PO No.",
	},
	{
		name:"Dealer",
		title:"Customer",
	},
	{
		name:"Dealer_order_amount",
		title:"Net Amount",
	},
	{
		name:"stock_point",
		title:"Stock Point",
		callback:function(val){
			return val[0]
		}
	},
	{
		name:"Dealer_order_date",
		title:"Order Date",
		callback:function(val){
			return moment.unix(val).format('DD MMM YYYY')
		}
	},
  	{
    	name: '__slot:actions',
    	title: '',
    	dataClass:"shrink",
    	titleClass:"shrink"
  	}
]