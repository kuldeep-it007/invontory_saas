<template>
	<div>
		<section class="content-header">
			<h1>Shipping Address <span class="pull-right"><button class="btn btn-sm btn-default" @click="displayAddAddressModal">ADD SHIPPING ADDRESS</button></span></h1>
		</section>
		<section class="content">
			<div class="box box-widget">
				<div class="box-body">
					<div class="col-md-6" v-for="(address,index) in addresses" :key="address.code">
						<div class="box box-default box-solid">
            				<div class="box-body address">
              					<h1>{{address.name}}</h1>
								<h2>{{address.contact}}</h2>
								<p>{{address.address}}</p>
								<p>{{address.address_2}}</p>
								<p>{{address.city}} {{address.state.name}} {{address.post_code}}</p>
								<p>{{address.country_region_code}}</p>
								<p><abbr title="Phone">P:</abbr> {{address.phone_no}}</p>
								<div class="actions">
									<a href="#" @click.prevent="editAddress(index,address)"><i class="fa fa-pencil"></i></a>
									<a href="#" @click.prevent="deleteAddress(index,address)"><i class="fa fa-times-circle"></i></a>
								</div>
            				</div>
          				</div>
					</div>
					<p v-if="loaded && !addresses.length"> No shipping addresses present</p>
				</div>
			</div>
		</section>
		<address-modal ref="addressModal" :address="currentAddress" :company="effectiveCompany" :distributor="effectiveDistributor" :superStockist="effectiveSuperStockist" :dealer="effectiveDealer" :retailCustomer="effectiveRetailCustomer" @address:added="addressAdded" @address:edited="addressEdited"></address-modal>
	</div>
	
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	import AddressModal from './Modal'
	//import CompanyList from './List'
	export default{
		mixins : [appMixin],
		components:{
			AddressModal
		},
		data(){
			return {
				addresses:[],
				loaded:false,
				currentAddress:{
					code:null,
					address: "",
					address_2: "",
					post_code: "",
					city: "",
					country_region_code: "",
					state: "",
					phone_no: "",
					name: "",
					contact: ""
				},
				effectiveCompany:{
					id:this.$route.params.id
				},
				effectiveDistributor:{
					id:null
				},
				effectiveSuperStockist:{
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
				url = `companies/${this.$route.params.id}/distributors/${this.$route.params.distributorid}/addresses`
			}
			if(this.$route.meta.type == 'superStockist')
			{
				url = `companies/${this.$route.params.id}/super_stockists/${this.$route.params.super_stockist_id}/addresses`
			}
			if(this.$route.meta.type == 'dealer')
			{
				url = `companies/${this.$route.params.id}/dealers/${this.$route.params.dealerid}/addresses`
			}
			if(this.$route.meta.type == 'retailCustomer')
			{
				url = `companies/${this.$route.params.id}/retailCustomers/${this.$route.params.retailCustomerId}/addresses`
			}
			EventBus.$emit('loader:show')
			Vue.axios.get(url)
			.then(response => {
				EventBus.$emit('loader:hide')
				this.loaded = true
				this.addresses = response.data
			})
			if(this.$route.params.distributorid)
			{
				this.effectiveDistributor.id = this.$route.params.distributorid
			}
			if(this.$route.params.super_stockist_id)
			{
				this.effectiveSuperStockist.id = this.$route.params.super_stockist_id
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
			deleteAddress(index,address){
				swal({
                    title: "Delete address?",
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
							url = `companies/${this.$route.params.id}/distributors/${this.$route.params.distributorid}/addresses/${encodeURIComponent(address.code)}`
						}
						if(this.$route.meta.type == 'superStockist')
						{
							url = `companies/${this.$route.params.id}/super_stockists/${this.$route.params.super_stockist_id}/addresses/${encodeURIComponent(address.code)}`
						}
						if(this.$route.meta.type == 'dealer')
						{
							url = `companies/${this.$route.params.id}/dealers/${this.$route.params.dealerid}/addresses/${encodeURIComponent(address.code)}`
						}
						if(this.$route.meta.type == 'retailCustomer')
						{
							url = `companies/${this.$route.params.id}/retailCustomers/${this.$route.params.retailCustomerId}/addresses/${encodeURIComponent(address.code)}`
						}
                    	EventBus.$emit('loader:show')
                    	Vue.axios.delete(url)
                    	.then(response => {
                    		EventBus.$emit('loader:hide')
              				this.addresses.splice(index,1)      		
                    	})
                    	.catch(error=>{
                    		EventBus.$emit('loader:hide')
                    	})
                    }
                })
                .catch()
				//
			},
			editAddress(index,address){
				let self = this
				this.currentAddress = Object.assign({},address)
				Vue.nextTick(function(){
					self.$refs.addressModal.show()
				})
			},
			displayAddAddressModal(){
				this.$refs.addressModal.show()
			},
			addressEdited(address){
				let index = _.findIndex(this.addresses,function(element){
					return element.code == address.code
				})
				console.log('index is',index)
				this.addresses.splice(index,1,address)
			},
			addressAdded(address)
			{
				this.addresses.unshift(address)
			}
		}
	}
</script>
<style scoped>
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