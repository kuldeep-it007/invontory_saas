export default[
	{
		name:"number",
		title:"SO No.",
		callback:function(val){
			return val.replace('PO', 'SO')
		}
	},
	{
		name:"distributor.name",
		title:"Distributor"
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