export default[
	{
		name:"number",
		title:"Order No.",
		dataClass:"shrink",
		titleClass:"shrink"
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
  	}
]