<template>
	<div v-if="abilities.companies || abilities.viewSuperStockists">
		<section class="content-header">
			<h1>Super Stockists 
				<span class="pull-right">
					<router-link class="btn btn-default btn-sm" :to="{name:'companies.super_stockists.create'}" v-if="abilities.createSuperStockists"> ADD SUPER STOCKIST</router-link>
					<router-link class="btn btn-default btn-sm" :to="{name:'companies.super_stockists.sync'}" v-if="abilities.createSuperStockists"> SYNC SUPER STOCKIST</router-link>
				</span>
			</h1>
		</section>
		<section class="content">
			<superStockist-list></superStockist-list>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import SuperStockistList from './List'
	export default{
		mixins : [appMixin],
		components:{
			SuperStockistList
		}
	}
</script>