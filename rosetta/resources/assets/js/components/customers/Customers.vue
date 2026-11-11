<template>
	<div>
		<section class="content-header">
			<h1>Customer <span class="pull-right"><button class="btn btn-sm btn-default" @click="displayAddCustomerModal">ADD CUSTOMER</button></span></h1>
		</section>
		<section class="content">
			<div class="box box-widget">
				<div class="box-body">
					<div class="col-md-6" v-for="(customer,index) in customers" :key="customer.id">
						<div class="box box-default box-solid">
            				<div class="box-body customer">
              					<h1>{{customer.name}}</h1>
							<!--	<h2>{{customer.contact}}</h2>
								<p>{{customer.address}}</p>
								<p>{{customer.address_2}}</p>
								<p>{{customer.city}} {{customer.state.name}} {{customer.post_code}}</p>
								<p>{{customer.country_region_code}}</p>-->
								<p><abbr title="Phone">P:</abbr> {{customer.phone_no}}</p>
								<div class="actions">
									<a href="#" @click.prevent="editCustomer(index,customer)"><i class="fa fa-pencil"></i></a>
									<a href="#" @click.prevent="deleteCustomer(index,customer)"><i class="fa fa-times-circle"></i></a>
								</div>
            				</div>
          				</div>
					</div>
					<p v-if="loaded && !customers.length"> No Customers present</p>
				</div>
			</div>
		</section>
		<customer-modal ref="customerModal" :customer="currentCustomer" :company="effectiveCompany" :distributor="effectiveDistributor" :dealer="effectiveDealer" :retailCustomer="effectiveRetailCustomer" @customer:added="customerAdded" @customer:edited="customerEdited"></customer-modal>
	</div>
	
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	import CustomerModal from './Modal'
	//import CompanyList from './List'
	export default{
		mixins : [appMixin],
		components:{
			CustomerModal
		},
		data(){
			return {
				customers:[],
				loaded:false,
				currentCustomer:{
					id:null,
				/*	address: "",
					address_2: "",
					post_code: "",
					city: "",
					country_region_code: "",
					state: "",*/
					phone_no: "",
					name: "",
				//	contact: ""
				},
				effectiveCompany:{
					id:this.$route.params.id
				},
				effectiveDistributor:{
					id:null
				},
				effectiveDealer:{
					id:null
				},
				effectiveRetailCustomer:{
					id:null
				}
			}
		},
		mounted(){
			let url = ""
			if(this.$route.meta.type == 'distributor')
			{
				url = `companies/${this.$route.params.id}/distributors/${this.$route.params.distributorid}/customers`
			}
			if(this.$route.meta.type == 'dealer')
			{
				url = `companies/${this.$route.params.id}/dealers/${this.$route.params.dealerid}/customers`
			}
			if(this.$route.meta.type == 'retailCustomer')
			{
				url = `companies/${this.$route.params.id}/retailCustomers/${this.$route.params.retailCustomerId}/customers`
			}
			EventBus.$emit('loader:show')
			Vue.axios.get(url)
			.then(response => {
				EventBus.$emit('loader:hide')
				this.loaded = true
				this.customers = response.data
			})
			if(this.$route.params.distributorid)
			{
				this.effectiveDistributor.id = this.$route.params.distributorid
			}
			if(this.$route.params.dealerid)
			{
				this.effectiveDealer.id = this.$route.params.dealerid
			}
			if(this.$route.params.retailCustomerId)
			{
				this.effectiveRetailCustomer.id = this.$route.params.retailCustomerId
			}
		},
		methods:{
			deleteCustomer(index,customer){
				swal({
                    title: "Delete customer?",
                    icon:'warning',
                    buttons: {
                        cancel: true,
                        confirm: true,
                    }
                })
                .then(value => {
                    if(value !== null)
                    {
                    	let url = ""
						if(this.$route.meta.type == 'distributor')
						{
							url = `companies/${this.$route.params.id}/distributors/${this.$route.params.distributorid}/customers/${encodeURIComponent(customer.id)}`
						}
						if(this.$route.meta.type == 'dealer')
						{
							url = `companies/${this.$route.params.id}/dealers/${this.$route.params.dealerid}/customers/${encodeURIComponent(customer.id)}`
						}
						if(this.$route.meta.type == 'retailCustomer')
						{
							url = `companies/${this.$route.params.id}/retailCustomers/${this.$route.params.retailCustomerId}/customers/${encodeURIComponent(customer.id)}`
						}
                    	EventBus.$emit('loader:show')
                    	Vue.axios.delete(url)
                    	.then(response => {
                    		EventBus.$emit('loader:hide')
              				this.customers.splice(index,1)      		
                    	})
                    	.catch(error=>{
                    		EventBus.$emit('loader:hide')
                    	})
                    }
                })
                .catch()
				//
			},
			editCustomer(index,customer){
				let self = this
				this.currentCustomer = Object.assign({},customer)
				Vue.nextTick(function(){
					self.$refs.customerModal.show()
				})
			},
			displayAddCustomerModalModal(){
				this.$refs.customerModal.show()
			},
			customerEdited(customer){
				let index = _.findIndex(this.customers,function(element){
					return element.id == customer.id
				})
				console.log('index is',index)
				this.customers.splice(index,1,customer)
			},
			customerAdded(customer)
			{
				this.customers.unshift(customer)
			}
		}
	}
</script>
<style scoped>
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
.actions{
	position: absolute;
    top: 0;
    right: 5px;
    color:#666;
}
.actions a{
	color:#666;
}
</style>