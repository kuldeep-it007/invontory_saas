export default[
	{
		name:"number",
		title:"AO No.",
		callback:function(val){
			return val.replace('PO', 'SO')
		}
	//	dataClass:"shrink",
	//	titleClass:"shrink"
	},
	{
		name:"number",
		title:"Dealer AO No.",
	//	dataClass:"shrink",
	//	titleClass:"shrink"
	},
	/*{
		name:"so",
		title:"SO No.",
		dataClass:"shrink",
		titleClass:"shrink"
	},*/
	{
		name:"__slot:customer.name",
		title:"Customer",

	},
	{
		name:"amount",
		title:"Net Amount",
	//	dataClass:"shrink",
    //	titleClass:"shrink"
	},
	{
		name:"created_at",
		title:"Order Date",
		callback:function(val){
			return moment.unix(val).format('DD MMM YYYY')
		}
	},
	{
    	name: '__slot:status',
    	title: 'Status',
    	dataClass:'text-uppercase',
    	//titleClass:"shrink"
  	},
  	{
    	name: '__slot:actions',
    	title: '',
    	dataClass:"shrink",
    	titleClass:"shrink"
  	}
]