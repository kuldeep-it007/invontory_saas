export default[
	{
		name:"__slot:number",
		title:"AO No.",
	},
	{
		name:"stock_points",
		title:"Stock Point",
		dataClass:"shrink",
		titleClass:"shrink",
		callback:(val) => {
			return _.reject(val, function(n){
				return n == null;
			}).join('<br>')
		}
	},//#762
	{
		name:"so_numbers",
		title:"SO No.",
		dataClass:"shrink",
		titleClass:"shrink",
		callback:(val) => {
			return val.join('<br>')
		}
	},
	{
		name:"si_numbers",
		title:"SI No.",
		dataClass:"shrink",
		titleClass:"shrink",
		callback:(val) => {
			return _.reject(val, function(n){
				return false;
			}).join('<br>')
		}
	},
	{
		name:"__slot:customer.name",
		title:"Customer",

	},
	{
		name:"net_amount",
		title:"Net Amount"
	},
	{
		name:"created_at",
		title:"Order Date",
		callback:function(val){
			return moment.unix(val).format('DD MMM YYYY')
		}
	},
	{
	/*	name:"dealer_name",
		title:"Dealer Order"*/
		name:"__slot:dealer_name",
		title:"Dealer Order",
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