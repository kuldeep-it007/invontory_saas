	export default[
	{
		name:"id",
		title:"ID",
		visible:false,
	},
	{
		name:"product.nav_id",
		title:"ID",
		dataClass:"shrink",
		titleClass:"shrink",
	},
	{
		name:"product.category.display_name",
		title:"Category"
	},
	{
		name:"product.collection.display_name",
		title:"Collection"
	},
	{
		name:"product.colour.display_name",
		title:"Colour"
	},
	{
		name: "batch_no",
		title: "Batch No"
	},
	{
    	name: '__slot:inventory',
    	title: 'Stock',
		dataClass:"shrink",
		titleClass:"shrink",
  	},
  	{
    	name: '__slot:blocked',
    	title: 'Blocked',
		titleClass:"no-wrap",
		dataClass:"no-wrap"
  	},
  	{
    	name: '__slot:actions',
    	title: '',
    	titleClass: 'text-right shrink',
    	dataClass: 'text-right shrink'
  	}
  	/*{
    	name: '__slot:unit-price',
    	title: 'RRP',
		titleClass:"no-wrap",
		dataClass:"no-wrap"
  	},
  	{
    	name: 'price.discount',
    	title: 'Disc.',
		titleClass:"no-wrap",
		dataClass:"no-wrap",
		callback:function(value){
			return `${value}%`
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
  	}*/
]