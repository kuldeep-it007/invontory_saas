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
			        		<div class="custom-actions">
			        			<router-link :to="{name:'collections.colours',params:{collection_code:encodeURIComponent(props.rowData.code)}}" title="view colours">
			        				colours
			        			</router-link>
			        		</div>
			      		</template>
					</vuetable>
				</div>
			</div>
		</div>
	</div>
</template>
<script>
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import CollectionListColumns from './ListFields.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import Vue from 'vue'
	export default{
		components:{
			Vuetable,
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:CollectionListColumns,
				apiUrl:`categories/${encodeURIComponent(this.$route.params.category_code)}/collections`
			}
		},
		methods:{
			reload(){
				this.$refs.vuetable.refresh()	
			}
		}
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