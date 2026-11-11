<template>
	<div class="row">
		<div class="col-md-12">
			<div class="box box-widget">
				<div class="box-body table-responsive">
					<vuetable 
						:fields="tableFields"
						:api-url="apiUrl"
						data-path=""
						pagination-path=""
						ref="vuetable"
						:css="css.table">
			      		<template slot="actions" slot-scope="props" >
			      		</template>
					</vuetable>
				</div>
			</div>
		</div>
	</div>
</template>
<script>
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import ColourListColumns from './ListFields.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import Vue from 'vue'
	export default{
		components:{
			Vuetable,
		},
		methods:{
			reload(){
				this.$refs.vuetable.refresh()
			}
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:ColourListColumns,
				apiUrl:`categories/${encodeURIComponent(this.$route.params.category_code)}/collections/${encodeURIComponent(this.$route.params.collection_code)}/colours`
			}
		},
	}
</script>
<style>
	.has-actions{
		width: 1%;
		white-space: nowrap;
	}
	.vuetable-th-id{
		width: 1%;
		white-space: nowrap;	
	}
</style>