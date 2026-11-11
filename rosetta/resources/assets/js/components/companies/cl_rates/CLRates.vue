<template>
	<div v-if="abilities.viewCLRates">
		<section class="content-header">
			<h1>CL Rates <span class="pull-right"><button class="btn btn-sm btn-primary" @click="showAddRateModal" v-if="abilities.addCLRates && selectedCompany">ADD</button></span></h1>
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
						<div class="col-md-3" v-if="selectedRegion">
							<div class="form-group">
								<label>Company</label>
								<company v-model="selectedCompany" :region="selectedRegion"></company>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="table-responsive" v-if="selectedCompany">
				<rates :company="selectedCompany" ref="list"></rates>
			</div>
			<add-rate :company="selectedCompany" v-if="selectedCompany" ref="addModal" @cl_rate:added="CLRateAdded"></add-rate>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../../vuex-mixin.js'
	import Rates from './List'
	import Region from '../../region/Select'
	import Company from '../../companies/Select'
	import AddRate from './Modal'
	export default{
		mixins : [appMixin],
		components:{
			Rates,
			Region,
			Company,
			AddRate,
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
			showAddRateModal(){
				this.$refs.addModal.show()
			},
			CLRateAdded(){
				this.$refs.list.reload()
			}
		}
	}
</script>