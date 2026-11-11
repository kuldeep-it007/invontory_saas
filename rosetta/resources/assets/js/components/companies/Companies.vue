<template>
	<div v-if="abilities.companies">
		<section class="content-header">
			<h1>Companies <span class="pull-right"><router-link :to="{name:'companies.create'}" class="btn btn-default">ADD</router-link></span></h1>
		</section>
		<section class="content">
			<company-list></company-list>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import CompanyList from './List'
	export default{
		mixins : [appMixin],
		components:{
			CompanyList
		}
	}
</script>