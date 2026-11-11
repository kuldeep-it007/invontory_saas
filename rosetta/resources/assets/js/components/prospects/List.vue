<template>
	<div>
		<div class="row">
			<div class="col-md-12">
				<a class="pull-right" :class="hasFilters? 'text-green':''" href="#" @click.prevent="filters=!filters"><i class="fa fa-filter"></i></a>
			</div>
		</div>
		<div v-show="filters">
			<div class="row">
				<div class="col-md-3">
					<div class="form-group">
						<label>Email</label>
						<input class="form-control" type="text" v-model="email">
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group">
						<label>Phone</label>
						<input class="form-control" type="text" v-model="phone">
					</div>
				</div>
			</div>
			<div class="col-md-1">
                <div class="form-group">
                    <label style="display: block;">&nbsp;</label>
                    <button class="btn btn-info btn-block" @click="search"><i class="fa fa-search"></i></button>
                </div>
            </div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<div class="box box-widget">
					<div class="box-body">
						<vuetable 
							:fields="tableFields"
							api-url="prospects"
							pagination-path="pagination"
							data-path="prospects"
							:per-page="perPage"
							ref="vuetable"
							:css="css.table"
							@vuetable:pagination-data="onPaginationData"
							:append-params="appendParams"
							>
						<!--	<template slot="actions" slot-scope="props" >
				        		<div class="custom-actions">
				        			<a href="#" @click.prevent="deleteProspect(props.rowData,props.rowIndex)"><i class="fa fa-times"></i></a>
				        		</div>
				      		</template>-->
						</vuetable>
					</div>
					<div class="box-footer">
						<div style="text-align:right;">
							<vuetable-pagination ref="pagination"
				        		:css="css.pagination"
				        		@vuetable-pagination:change-page="onChangePage"
				      		></vuetable-pagination>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
<script>
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import ProspectListColumns from './ListFields.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	export default{
		components:{
			Vuetable,
			VuetablePagination,
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:ProspectListColumns,
				perPage:100,
				email:null,
				phone:null,
				filters:false,
			}
		},
		computed:{
			appendParams(){
				let params = {}
				if(this.email)
					params.email = this.email
				if(this.phone)
					params.phone = this.phone
				return params
			},
			hasFilters(){
				return !_.isEmpty(this.appendParams)
			}
		},
		methods:{
			search(){
				this.$refs.vuetable.reload()
			},
			deleteProspect(prospect,index){
				swal({
                    title: "Delete Prospect?",
                    icon:'warning',
                    buttons: {
                        cancel: true,
                        confirm: true,
                    }
                })
                .then(value=> {
                	if(value)
                	{
                		EventBus.$emit('loader:show')
                		Vue.axios.delete(`prospects/${prospect.id}`)
                		.then(response => {
                			EventBus.$emit('loader:hide')
                			swal({
                    			title: "Prospect deleted.",
                    			icon:'success',
                    		})
                    		.then(value =>{
                    			this.$refs.vuetable.tableData.splice(index,1)		
                    		})
                		})
                		.catch(error => {
                			EventBus.$emit('loader:hide')
                		})
                	}
                })
			},
			onPaginationData (tablePagination) {
		      this.$refs.pagination.setPaginationData(tablePagination)
		    },
		    transform: function(data){
		    	var transformed = {}
		    	transformed.pagination = {

		    		total:data.total,
		    		per_page :this.perPage,
		    		current_page: data.page,
		    		last_page: Math.ceil(data.total / this.perPage),
		    		from: ((data.page-1) * this.perPage) +1,
		    		to:((data.page-1) * this.perPage) + data.prospects.length
		    	}
		    	transformed.prospects = data.prospects
		    	return transformed
		    },
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
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