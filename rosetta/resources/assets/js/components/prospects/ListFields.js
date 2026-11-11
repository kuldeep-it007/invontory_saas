export default [
	{
		name:"name",
		title: "Name",
	},
  	{
    	name: 'email',
    	title: 'Email',
  	},
  	{
    	name: 'phone',
    	title: 'Phone',
  	},
  	{
    	name: 'business_name',
    	title: 'Firm Name',
  	},
  	{
    	name: 'receive_updates',
    	title: 'Receive Updates',
		callback:function(val){
			return val ? '<i class="fa fa-check"></i>' : ''
		},
		dataClass:'shrink text-center',
		titleClass:'shrink'
  	},
  	{
    	name: 'gst_no',
    	title: 'GST #',
  	},
  	{
    	name: 'pan',
    	title: 'PAN',
  	},
  	{
    	name: 'floor',
    	title: 'Floor',
  	},
  	{
    	name: 'street',
    	title: 'Street',
  	},
  	{
    	name: 'city',
    	title: 'City',
  	},
  	{
    	name: 'state',
    	title: 'State',
  	},
  	{
    	name: 'post_code',
    	title: 'Post Code',
  	},
/*	{
    	name: '__slot:actions',
    	title: '',
    	titleClass: 'text-right has-actions',
    	dataClass: 'text-right has-actions'
  	}*/
];