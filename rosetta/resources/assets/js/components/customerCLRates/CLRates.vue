<template>
	<div v-if="abilities.viewCLRates">
		<section class="content-header">
			<h1>CL Rates <span class="pull-right"><button class="btn btn-sm btn-primary" @click="showAddRateModal" v-if="abilities.addCLRates">ADD</button></span></h1>
		</section>
		<section class="content">
			<div class="table-responsive">
				<rates ref="list"></rates>
			</div>
			<add-rate ref="addModal" @cl_rate:added="CLRateAdded"></add-rate>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Rates from './List'
	import Region from '../region/Select'
	import Company from '../companies/Select'
	import AddRate from './Modal'
	export default{
		mixins : [appMixin],
		components:{
			Rates,
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