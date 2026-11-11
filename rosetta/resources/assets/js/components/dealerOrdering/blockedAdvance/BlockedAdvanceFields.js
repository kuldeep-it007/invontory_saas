export default[
	{
		name:"__checkbox",
	},
	/*{
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
	},*/
	{
    	name: '__slot:product',
    	title: 'Product'
  	},
	{
		name:"stock_point_name",
		title:"Stock Point"
	},
	{
		name:"nav_intransit_po",
		title:"Batch No"
	},
	{
    	name: '__slot:quantity',
    	title: 'Quantity'
  	},
	{
		name:"unit_price",
		title:"RRP"
	},
	{
		name: '__slot:discount',
		title:"Discount %",
		dataClass:"no-padding"
	},
	{
    	name: '__slot:discounted-price',
    	title: 'Disc. Price'
  	},
  	{
    	name: '__slot:amount',
    	title: 'Net Amount'
  	},
  	{
    	name: '__slot:tax_amount',
    	title: 'Tax Amount'
  	},
  	{
    	name: '__slot:net_amount',
    	title: 'Total Amount'
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
        name: 'expires',
        title: 'Discount Validity',
        callback:function(val){
            return val ? moment.unix(val).format('DD MMM YYYY') : null
        },
        titleClass: "shrink",
        dataClass:"shrink",
    },
    {
        name:"eta",
        title:"ETA",
        callback:function(val){
            return val ? moment.unix(val).format('DD MMM YYYY') : null
        },
        titleClass: "shrink",
        dataClass:"shrink"
    },
  	{
    	name: '__slot:actions',
    	title: ''
  	}

]