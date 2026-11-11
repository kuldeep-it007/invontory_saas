export default[
	{
		name:"id",
		title:"ID",
		dataClass:"shrink",
		titleClass:"shrink"
	},
	{
		name:"name",
		title:"Dealer",
		titleClass: "shrink",
        dataClass:"shrink"
	},
	{
		name:"rejection_reason",
		title:"Rejection Reason",
	},
	{
        name:"created_at",
        title:"Added On",
        callback:function(val){
            return moment.unix(val).format('DD MMM YYYY')
        },
        titleClass: "shrink",
        dataClass:"shrink"
    },
	{
    	name: '__slot:actions',
    	title: '',
    	titleClass: 'shrink text-right has-actions',
    	dataClass: 'shrink text-right has-actions'
  	},
]