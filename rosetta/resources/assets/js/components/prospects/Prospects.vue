<template>
	<div v-if="abilities.viewProspects">
		<section class="content-header">
			<h1>App Prospects <span class="pull-right"></span></h1>
		</section>
		<section class="content">
			<div class="table-responsive">
				<prospects></prospects>
			</div>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Prospects from './List'
	export default{
		mixins : [appMixin],
		components:{
			Prospects,
		},
		methods:{
		}
	}
</script>