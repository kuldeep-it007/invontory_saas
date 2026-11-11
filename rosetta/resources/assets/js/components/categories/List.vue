<template>
	<div class="row">
		<div class="col-md-12">
			<div class="box box-widget">
				<div class="box-body table-responsive">
					<vuetable 
						:fields="tableFields"
						api-url="categories"
						data-path=""
						pagination-path=""
						ref="vuetable"
						:css="css.table">
						<template slot="actions" slot-scope="props" >
			        		<router-link :to="{name:'categories.collections',params:{category_code:encodeURIComponent(props.rowData.code)}}" title="view collections">
			        				collections
			        			</router-link>
			      		</template>
			      		<template slot="cl_percent" slot-scope="props" >
			        		<template v-if="props.rowData.allow_cl">
			        			{{props.rowData.cl_percent}}%
			        		</template>
			        		<template v-else>
			        			&nbsp;
			        		</template>
			      		</template>
					</vuetable>
				</div>
			</div>
		</div>
	</div>
</template>
<script>
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import CategoryListColumns from './ListFields.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import Vue from 'vue'
	export default{
		components:{
			Vuetable,
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:CategoryListColumns,
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