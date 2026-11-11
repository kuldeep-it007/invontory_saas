<template>
	<div v-if="abilities.manageCoDistributorDiscounts">
		<section class="content-header">
			<h1>Discounts <span class="pull-right"><button class="btn btn-sm btn-primary" @click="addDiscount" v-if="abilities.addCoDistributorDiscounts && selectedDistributor">ADD</button></span></h1>
		</section>
		<section class="content">
			<div class="box box-widget" v-if="!isCustomer">
				<div class="box-body">
					<div class="row">
						<div class="col-md-3" v-if="!state.user.company">
							<div class="form-group">
								<label>Region</label>
								<region v-model="selectedRegion"></region>
							</div>
						</div>
						<div class="col-md-3" v-if="selectedRegion && !state.user.company">
							<div class="form-group">
								<label>Company</label>
								<company v-model="selectedCompany" :region="selectedRegion"></company>
							</div>
						</div>
						<div class="col-md-3" v-if="selectedCompany">
							<div class="form-group">
								<label>Distributor</label>
								<distributor ref="distributor" v-model="selectedDistributor" :customer_type="customerType" :company="selectedCompany"></distributor>
							</div>
						</div>
					</div>
				</div>
			</div>
			<discounts ref="Discount" v-if="selectedDistributor" :use-distributor="selectedDistributor" :company="selectedCompany"></discounts>
			<discount-modal 
				ref="discountModal" 
				:company="selectedCompany" 
				:distributor="selectedDistributor" 
				@discount-added="onDiscountAdded"
				v-if="selectedDistributor"></discount-modal>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import Vue from 'vue'
	import appMixin from '../../../vuex-mixin.js'
	import Discounts from './List'
	import Region from '../../region/Select'
	import Company  from '../../companies/Select'
	import Distributor from '../../master/CustomerSelect'
	import DiscountModal from './Modal'
	export default{
		mixins : [appMixin],
		components:{
			Discounts,
			Region,
			Company,
			Distributor,
			DiscountModal,
		},
		data(){
			return {
				selectedDistributor:null,
				selectedCompany:null,
				selectedRegion:null,
				customerType:{
					code:"DISTRIBUTO",
					name:"Distributor"
				}
			}
		},
		mounted(){
			if(this.customer)
			{
				this.selectedDistributor = this.distributor
				this.selectedCompany = this.distributor.company
			}
			else
			{
				if(this.state.user.company)
				{
					this.selectedRegion = this.state.user.company.region
					this.selectedCompany = this.state.user.company
				}

			}
		},
		methods:{
			addDiscount(){
				this.$refs.discountModal.show()
			},
			onDiscountAdded(discount){
        		this.$refs.Discount.reload()
            },
		},
		watch:{
			selectedCompany(){
				let self = this
				Vue.nextTick(function(){
					if(self.selectedCompany && !self.isCustomer)
						self.$refs.distributor.loadCustomers()	
				})
				
			}
		}
	}
</script>