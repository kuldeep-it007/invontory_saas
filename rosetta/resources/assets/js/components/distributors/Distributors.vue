<template>
	<div v-if="abilities.companies || abilities.viewDistributors">
		<section class="content-header">
			<h1>Distributors 
				<span class="pull-right">
					<router-link class="btn btn-default btn-sm" :to="{name:'companies.distributors.create'}" v-if="abilities.createDistributors && companyDistributors"> ADD DISTRIBUTOR</router-link>
					<router-link class="btn btn-default btn-sm" :to="{name:'companies.distributors.sync'}" v-if="abilities.createDistributors && companyDistributors"> SYNC DISTRIBUTOR</router-link>
					<router-link class="btn btn-default btn-sm" :to="{name:'companies.super_stockists.distributors.create'}" v-if="abilities.createDistributors && superStockistDistributors"> ADD DISTRIBUTOR</router-link>
					<router-link class="btn btn-default btn-sm" :to="{name:'companies.super_stockists.distributors.sync'}" v-if="abilities.createDistributors && superStockistDistributors"> SYNC DISTRIBUTOR</router-link>
				</span>
			</h1>
		</section>
		<section class="content">
			<distributor-list></distributor-list>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import DistributorList from './List'
	export default{
		mixins : [appMixin],
		components:{
			DistributorList
		},
		computed:{
			companyDistributors(){
				return !this.$route.params.super_stockist_id
			},
			superStockistDistributors(){
				return !(!this.$route.params.super_stockist_id)
			}
		}
	}
</script>