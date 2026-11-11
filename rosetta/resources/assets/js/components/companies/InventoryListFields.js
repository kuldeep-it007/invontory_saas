export default[
	{
		name:"id",
		title:"ID",
		visible:false,
	},
	{
		name:"nav_id",
		title:"ID",
		titleClass:"no-wrap",
		dataClass:"no-wrap"
	},
	{
		name:"__slot:category",
		title:"Category"
	},
	{
		name:"__slot:collection",
		title:"Collection"
	},
	{
		name:"__slot:colour",
		title:"Colour"
	},
	{
    	name: '__slot:inventory',
    	title: 'Stock',
		titleClass:"no-wrap",
		dataClass:"no-wrap"
  	},
  	{
    	name: '__slot:blocked',
    	title: 'Blocked',
		titleClass:"no-wrap",
		dataClass:"no-wrap"
  	},
  	{
    	name: '__slot:unit-price',
    	title: 'RRP',
		titleClass:"no-wrap",
		dataClass:"no-wrap"
  	},
	{
    	name: '__slot:actions',
    	title: '',
    	titleClass: 'text-right has-actions',
    	dataClass: 'text-right has-actions'
  	}
]