export default[
	{
		name:"number",
		title:"PO No."
	},
	{
		name:"co_distributor.name",
		title:"Co Distributor"
	},
	{
		name:"amount",
		title:"Amount",
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