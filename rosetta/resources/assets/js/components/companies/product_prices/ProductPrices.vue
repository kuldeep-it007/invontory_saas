<template>
	<div v-if="abilities.viewProductPrices">
		<section class="content-header">
			<h1>MUP</h1>
		</section>
		<section class="content">
			<div class="box box-widget" v-if="!state.user.company">
				<div class="box-body">
					<div class="row">
						<div class="col-md-3">
							<div class="form-group">
								<label>Region</label>
								<region v-model="selectedRegion"></region>
							</div>
						</div>
						<div class="col-md-5" v-if="selectedRegion">
							<div class="form-group">
								<label>Company</label>
								<company v-model="selectedCompany" :region="selectedRegion"></company>
							</div>
						</div>
						<div class="col-md-4" v-if="selectedCompany">
							<div class="form-group">
								<label style="display:block">&nbsp;</label>
		        				<button class="btn btn-success pull-right" @click="showAddPriceModal()" v-if="abilities.addProductPrices">Set Collection Price</button>
		        			</div>
		        		</div>
					</div>
				</div>
			</div>
			<div class="table-responsive" v-if="selectedCompany">
				<prices :company="selectedCompany" ref="list"></prices>
			</div>
		</section>
		<add-collection-price v-if="selectedCompany" :company="selectedCompany" ref="addCollectionPriceModal" @collection_price:added="CollectionPriceAdded"></add-collection-price>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../../vuex-mixin.js'
	import Prices from './List'
	import Region from '../../region/Select'
	import Company from '../../companies/Select'
	import AddCollectionPrice from './AddCollectionPriceModal'
	export default{
		mixins : [appMixin],
		components:{
			Prices,
			Region,
			Company,
			AddCollectionPrice,
			//AddPrice,
		},
		data(){
			return {
				selectedCompany:null,
				selectedRegion:null,
			}
		},
		mounted(){
			if(this.state.user.company)
				this.selectedCompany = Object.assign({},this.state.user.company)
		},
		methods:{
			showAddPriceModal(){
				this.$refs.addCollectionPriceModal.show()
			},
			CollectionPriceAdded(mup){
			},
		}
	}
</script>