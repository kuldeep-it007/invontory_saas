<template>
	<div class="row">
		<div class="col-md-10">
			<multiselect 
				v-model="address"
	      		:allow-empty="false" 
	      		:options="addresses" 
	      		label="code" 
	      		track-by="code" 
	      		placeholder="Select Address" 
	      		:show-labels="false"
	      		:custom-label="customLabel"
	      		@select="addressSelected" 
	      		@remove="addressRemoved" 
	      		>
	      		<template slot="option" slot-scope="props">
	      			<div class="box-body address">
						<h1>{{props.option.name}}</h1>
						<h2>{{props.option.contact}}</h2>
						<p>{{props.option.address}}</p>
						<p>{{props.option.address_2}}</p>
						<p>{{props.option.city}} {{props.option.state.name}} {{props.option.post_code}}</p>
						<p>{{props.option.country_region_code}}</p>
						<p><abbr title="Phone">P:</abbr> {{props.option.phone_no}}</p>
					</div>
	    		</template>
	      	</multiselect>	
	    </div>
	    <div class="col-md-2">
	    	<button class="btn btn-default btn-block" @click="$refs.addressModal.show()">ADD</button>
	    </div>
	    <create-address 
	    	ref="addressModal" 
	    	:company="company" 
	    	:distributor="distributor" 
	    	:superStockist="superStockist" 
	    	:dealer="dealer" 
	    	@address:added="addressAdded"
	    	:allow-customer-address="allowCustomerAddress"
	    ></create-address>
	</div>
</template>
<script>
	import Vue from 'vue'
	import Multiselect from 'vue-multiselect'
	import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
	import CreateAddress from './Modal'
	export default{
		props:{
			company:{
				type:Object,
				required: true,
			},
			distributor:{
				type:Object,
				required:false,
			},
			superStockist:{
				type:Object,
				required:false,
			},
			dealer:
			{
				type:Object,
				required:false,
			},
			allowCustomerAddress:{
				type:Boolean,
				required:false,
				default:false,
			}
		},
		components:{
			Multiselect,
			CreateAddress,
		},
		data(){
			return {
				addresses:[],
				address:null,
			}
		},
		mounted(){
			let url = ""
			if(this.distributor)
			{
				url = `companies/${this.company.id}/distributors/${this.distributor.id}/addresses`
			}
			if(this.superStockist)
			{
				url = `companies/${this.company.id}/super_stockists/${this.superStockist.id}/addresses`
			}
			if(this.dealer)
			{
				url = `companies/${this.company.id}/dealers/${this.dealer.id}/addresses`
			}
			Vue.axios.get(url)
			.then(response => {
				this.addresses = response.data
				this.address = this.addresses[0]
				this.$emit('input',this.address)
			})
		},
		methods:{
			addressAdded(address){
				this.addresses.unshift(address)
				this.addres = this.addresses[0]
				this.$emit('shipping-address:selected',address)
				this.$emit('input',address)
			},
			customLabel(address){
				return `${address.name} - ${address.contact}`
			},
			addressSelected(address){
				this.$emit('shipping-address:selected',address)
				this.$emit('input',address)
			},
			addressRemoved(){
				this.$emit('shipping-address:selected',null)
				this.$emit('input',null)
			}
		}
	}
</script>
<style>
.address{
	padding-bottom: 5px;
}
.address h1 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #666;
    font-size: 20px;
    font-weight: bold;

    /* text-align: center; */
}
.address h2 {
    margin-top: 0;
    /* margin-bottom: 0; */
    font-size: 18px;
}
.address p {
    margin-bottom: 5px;
}
</style>