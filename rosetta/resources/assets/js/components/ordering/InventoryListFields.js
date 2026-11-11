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
/*	{
    	name: '__slot:product',
    	title: 'Product'
  	},*/
  	{
		name:"category.display_name",
		title:"Category"
	},
	{
		name:"collection.display_name",
		title:"Collection"
	},
	{
		name:"colour.display_name",
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
    	name: '__slot:in_transit',
    	title: 'In-transit',
		titleClass:"no-wrap",
		dataClass:"no-wrap"
  	},
  	{
    	name: '__slot:in_transit_blocked',
    	title: 'In-transit Blocked',
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
    	name: 'price.discount',
    	title: 'Disc. %',
		titleClass:"no-wrap",
		dataClass:"no-wrap",
		callback:function(value){
			return `${value}`
		}
  	},
  	{
    	name: '__slot:price',
    	title: 'Disc. price',
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