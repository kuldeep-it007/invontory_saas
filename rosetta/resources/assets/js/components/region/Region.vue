<template>
	<div v-if="abilities.regions">
		<section class="content-header">
			<h1>Regions <span class="pull-right"><router-link :to="{name:'regions.create'}" class="btn btn-default">ADD</router-link></span></h1>
		</section>
		<section class="content">
			<region-list></region-list>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import RegionList from './List'
	export default{
		mixins : [appMixin],
		components:{
			RegionList
		}
	}
</script>