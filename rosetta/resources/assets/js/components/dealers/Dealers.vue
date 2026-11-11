<template>
	<div v-if="abilities.companies || abilities.viewDealers">
		<section class="content-header">
			<h1>Dealers 
				<span class="pull-right">
					<router-link class="btn btn-default btn-sm" :to="{name:'companies.dealers.create'}" v-if="abilities.createDealers && companyDealers"> ADD DEALER</router-link>
					<router-link class="btn btn-default btn-sm" :to="{name:'companies.distributors.dealers.create'}" v-if="abilities.createDealers && distributorDealers"> ADD DEALER</router-link>
					<router-link class="btn btn-default btn-sm" :to="{name:'companies.dealers.sync'}" v-if="abilities.createDealers && companyDealers"> SYNC DEALER</router-link>
					<router-link class="btn btn-default btn-sm" :to="{name:'companies.distributors.dealers.sync'}" v-if="abilities.createDealers && distributorDealers"> SYNC DEALER</router-link>
				</span>
			</h1>
		</section>
		<section class="content">
			<dealer-list></dealer-list>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import DealerList from './List'
	export default{
		mixins : [appMixin],
		components:{
			DealerList
		},
		computed:{
			companyDealers(){
				return !this.$route.params.distributorid
			},
			distributorDealers(){
				return !(!this.$route.params.distributorid)
			}
		}
	}
</script>