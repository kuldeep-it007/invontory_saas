export default[
	{
		name:"ref",
		title:"Ref",
		dataClass:"shrink",
		titleClass:"shrink"
	},
	{
		name:"created_at",
		title:"Date",
		dataClass:"shrink",
		titleClass:"shrink",
		callback:function(val){
			return moment.unix(val).format('DD MMM YYYY')
		}
	},
	{
		name:"__slot:customer.name",
		title:"Customer",

	},
	{
		name:"__slot:order",
		title:"Order",

	},
	{
		name:"amount",
		title:"amount"
	},
	{
		name:"status",
		title:"Status",
	},
	{
		name:"gateway",
		title:"Gateway",
		dataClass:"shrink",
		titleClass:"shrink"
	},

	/*{
		name:"so_numbers",
		title:"SO No.",
		dataClass:"shrink",
		titleClass:"shrink",
		callback:(val) => {
			return val.join(', ')
		}
	},
	{
		name:"__slot:customer.name",
		title:"Customer",

	},
	{
		name:"amount",
		title:"amount"
	},
	{
		name:"created_at",
		title:"Order Date",
		callback:function(val){
			return moment.unix(val).format('DD MMM YYYY')
		}
	},
	{
		name:"dealer_name",
		title:"Dealer Order"
	},
	{
    	name: '__slot:status',
    	title: 'Status',
    	dataClass:'text-uppercase shrink',
    	titleClass:"shrink"
  	},
  	{
    	name: '__slot:actions',
    	title: '',
    	dataClass:"shrink",
    	titleClass:"shrink"
  	}*/
]