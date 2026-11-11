<template>
	<div class="row">
		<div class="col-md-10">
			<multiselect 
				v-model="customer"
	      		:allow-empty="true" 
	      		:options="customers" 
	      		label="id" 
	      		track-by="id" 
	      		placeholder="Select Customer" 
	      		:show-labels="false"
	      		:custom-label="customLabel"
	      		@select="customerSelected" 
	      		@remove="customerRemoved" 
	      		>
	      		<template slot="option" slot-scope="props">
	      			<div class="box-body customer">
						<h1>{{props.option.name}}</h1>
					<!--	<h2>{{props.option.contact}}</h2>
						<p>{{props.option.address}}</p>
						<p>{{props.option.address_2}}</p>
						<p>{{props.option.city}} {{props.option.state.name}} {{props.option.post_code}}</p>
						<p>{{props.option.country_region_code}}</p>-->
						<p><abbr title="Phone">P:</abbr> {{props.option.phone_no}}</p>
					</div>
	    		</template>
	      	</multiselect>	
	    </div>
	    <div class="col-md-2">
	    	<button class="btn btn-default btn-block" @click="$refs.customerModal.show()">ADD</button>
	    </div>
	    <create-customer ref="customerModal" :company="company" :distributor="distributor" :dealer="dealer" @customer:added="customerAdded"></create-customer>
	</div>
</template>
<script>
	import Vue from 'vue'
	import Multiselect from 'vue-multiselect'
	import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
	import CreateCustomer from './Modal'
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
			dealer:
			{
				type:Object,
				required:false,
			}
		},
		components:{
			Multiselect,
			CreateCustomer,
		},
		data(){
			return {
				customers:[],
				customer:null,
			}
		},
		mounted(){
			let url = ""
			if(this.distributor)
			{
				url = `companies/${this.company.id}/distributors/${this.distributor.id}/customers`
			}
			if(this.dealer)
			{
				url = `companies/${this.company.id}/dealers/${this.dealer.id}/customers`
			}
			Vue.axios.get(url)
			.then(response => {
				this.customers = response.data
				//this.customer = this.customers[0]
				//this.$emit('input',this.customer)
			})
		},
		methods:{
			customerAdded(customer){
				this.customers.unshift(customer)
				this.customer = this.customers[0]
				this.$emit('customer:selected',customer)
				this.$emit('input',customer)
			},
			customLabel(customer){
				return `${customer.name} - ${customer.phone_no}`
			},
			customerSelected(customer){
				this.$emit('customer:selected',customer)
				this.$emit('input',customer)
			},
			customerRemoved(){
				this.$emit('customer:selected',null)
				this.$emit('input',null)
			}
		}
	}
</script>
<style>
.customer{
	padding-bottom: 5px;
}
.customer h1 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #666;
    font-size: 20px;
    font-weight: bold;

    /* text-align: center; */
}
.customer h2 {
    margin-top: 0;
    /* margin-bottom: 0; */
    font-size: 18px;
}
.customer p {
    margin-bottom: 5px;
}
</style>