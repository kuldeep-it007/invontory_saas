<template>
	<div v-if="abilities.viewProducts">
		<section class="content-header">
			<h1>Products
				
				<span class="pull-right">
					<router-link class="btn btn-default btn-sm" :to="{name:'products.create'}" v-if="abilities.manageProducts"> ADD PRODUCT</router-link>
				</span>
				
			</h1>
		</section>
		<section class="content">
			<product-list></product-list>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import ProductList from './List'
	export default{
		mixins : [appMixin],
		components:{
			ProductList
		}
	}
</script>