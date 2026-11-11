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
		name:"name",
		title:"Name"
	},
	{
		name:"category.description",
		title:"Category"
	},
	{
		name:"collection.description",
		title:"Collection"
	},
	{
		name:"colour.code",
		title:"Colour"
	},
	{
    	name: '__slot:inventory',
    	title: 'Stock',
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