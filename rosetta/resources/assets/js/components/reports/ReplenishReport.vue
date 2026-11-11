<template>
	<div v-if="abilities.viewProducts">
		<section class="content-header">
			<h1>Company Stock</h1>
		</section>
		<section class="content">
	        <div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body table-responsive">
							<div class="row">
								<div class="col-md-2">
									<input type="text" class="form-control" v-model="nav_id" placeholder="NAV ID">
								</div>
								<div class="col-md-2">
									<category v-model="category"></category>
								</div>
								<div class="col-md-2">
									<collection v-model="collection" :category="category"></collection>
								</div>
								<div class="col-md-2">
									<colour v-model="colour" :collection="collection"></colour>
								</div>
								<div class="col-md-2">
									<stockpoint v-model="stockpoint" :company="company"></stockpoint>
								</div>
								<div class="col-md-1">
									<button class="btn btn-default btn-block" @click="search"><i class="fa fa-search"></i></button>
								</div>
								<div class="col-md-1">
									<button class="btn btn-default" @click="clear">Clear</button>
								</div>
								<div class="clearfix"></div>								
							</div>
							<hr>
							<div class="row" style="margin-bottom: 5px;">
								<div class="col-md-12 text-right">
									<a href="#" class="text-danger" @click.prevent="deleteStock" v-if="checked"><i class="fas fa-trash-alt"></i> Delete</a>
									<a style="margin-left:10px;" href="#" @click.prevent="refreshStock"><i class="fa fa-sync-alt"></i> Refresh</a>
									<a style="margin-left:10px;" href="#" @click.prevent="exportStock"><i class="fas fa-upload"></i> Export</a>
									<a style="margin-left:10px;" href="#" @click.prevent="importStock"><i class="fas fa-download"></i> Import</a>
								</div>
							</div>
							<vuetable 
								:fields="tableFields"
								:api-url="url"
								data-path=""
								ref="vuetable"
								:css="css.table"
								pagination-path=""
								:append-params="appendParams"
								@vuetable:checkbox-toggled="checkboxToggled"
	                            @vuetable:checkbox-toggled-all="checkboxToggled"
								>
								<template slot="actions" slot-scope="props" v-if="abilities.updateUser">
									<div class="custom-actions">
										<button class="btn btn-xs btn-success" @click="updateThreshold(props.rowData)"><i class="fa fa-pencil-alt"></i></button>
									</div>
								</template>
							</vuetable>
						</div>
					</div>
				</div>
			</div>
		</section>
		<threshold-modal ref="thresholdModal" @threshold-updated="thresholdUpdated"></threshold-modal>
		<import-modal ref="importModal" :company="company" @threshold-updated="thresholdUpdated"></import-modal>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Stockpoint  from '../stockpoints/Select'
    import Vuetable from '../../vendor/vuetable2/Vuetable'
	//import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import fields from './replenish_fields'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import Category from '../categories/Search'
	import Collection from '../collections/Search'
	import Colour from '../colour/Search'
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	import { saveAs } from 'file-saver'
	import ThresholdModal from './ThresholdModal'
	import ImportModal from './ImportModal'
	export default{
		mixins : [appMixin],
		components:{
			Stockpoint,
            Vuetable,
			//VuetablePagination,
			Category,
			Collection,
			Colour,
			ThresholdModal,
			ImportModal,
		},
        data(){
			return {
				css:vuetableCssConfig,
				tableFields:fields,
				companies:[],
				company:null,
				nav_id:null,
				category:null,
				collection:null,
				colour:null,
				stockpoint:null,
                url:`companies/${this.$route.params.id}/stock_threshold`,
                company:{id:this.$route.params.id,
                		name:"AAD"
            	},
				checked : false
			}
		},
		computed:{
			appendParams(){
				let params = {}
				if(this.nav_id)
				{
					params.nav_id = this.nav_id
				}
				if(this.category)
				{
					params.category = this.category.code
				}
				if(this.collection)
				{
					params.collection = this.collection.code
				}
				if(this.colour)
				{
					params.colour = this.colour.code
				}
				if(this.stockpoint)
				{
					params.stockpoint = this.stockpoint.id
				}
				return params
			}
		},
		mounted(){
			this.company = Object.assign({},{id:this.$route.params.id})
        },
		methods:{
			search(){
				this.$refs.vuetable.refresh()
			},
			refreshStock(){
				EventBus.$emit('loader:show')
				Vue.axios.put(`companies/${this.$route.params.id}/stock_threshold`)
					.then(response => {
						//EventBus.$emit('loader:hide')
		                swal({
		                    title:"Sync Available Stock - Initiated!",
		                    text:"System has initiated sync process which will take a while, please revisit after some time.",
		                    icon:"success"
		                })
					})
			},
            exportStock(){
            	let params = {filters:{}}
            	if(this.category)
            		params.filters.category = this.category.code
            	if(this.collection)
            		params.filters.collection = this.collection.code
            	if(this.colour)
            		params.filters.colour = this.colour.code
            	if(this.stockpoint)
					params.filters.stockpoint = this.stockpoint.id

            	let url = `companies/${this.$route.params.id}/export_stock_threshold`

            	EventBus.$emit('loader:show')
                Vue.axios.post(url,{params:params},{responseType:'blob'})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    let disposition = response.headers['content-disposition']
                	let filename = decodeURI(disposition.match(/filename="(.*)"/)[1])
					saveAs(response.data, filename);

                })
                .catch(error => {
                	console.log(error)
                })
            },
            clear(){
            	let self = this
        		this.category = null
        		this.collection = null
        		this.colour = null
        		this.stockpoint = null
        		this.nav_id = null
        	//	this.search()
        	},
			checkboxToggled(){
				this.checked = this.$refs.vuetable.selectedTo.length != 0
			},
			deleteStock(){
				let self = this
				swal({
		    		title: `Delete  ${self.$refs.vuetable.selectedTo.length} items?`,
		    		icon:'warning',
  					buttons: {
    					cancel: true,
    					confirm: true,
  					},
				})
				.then(value => {
					if(value)
					{
						EventBus.$emit('loader:show')
						Vue.axios.delete(`companies/${this.$route.params.id}/stock_threshold`,{data:{
							ids : self.$refs.vuetable.selectedTo
						}})
						.then(response  => {
							self.$refs.vuetable.reload()
							EventBus.$emit('loader:hide')
						})
						.catch(error => {
							EventBus.$emit('loader:hide')
						})
					}
				})
			},
			updateThreshold(rec){
				console.log(rec)
				this.$refs.thresholdModal.show(rec)
			},
			thresholdUpdated(viaImport){
				this.$refs.vuetable.reload()
				if(viaImport)
					this.$refs.importModal.hide()
			},
			importStock(){
				this.$refs.importModal.show()
			}
		}
	}
</script>