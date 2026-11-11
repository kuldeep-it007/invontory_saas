<template>
    <div v-if="abilities.viewStockPoints">
        <section class="content-header">
            <h1>StockPoints</h1>
        </section>
        <section class="content">
            <div class="box box-widget" v-if="!state.user.company">
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-12">
                            <p>Select company to proceed</p>
                        </div>
                    </div>
                    <div class="row">

                        <div class="col-md-4">
                            <div class="form-group">

                                <label>Region</label>
                                <region-select v-model="region"></region-select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Company</label>
                                <company-select v-model="company" :region="region"></company-select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="box box-widget" v-if="abilities.addStockPoint">
                <div class="box-body" v-if="company">
                    <div class="row">
                        <div class="col-md-10">
                            <div class="form-group" :class="errors.has('stockpoint') ? 'has-error' : ''">
                                <label>Stock Point</label>
                                <input type="text" class="form-control" v-validate="'required'" data-vv-name="stockpoint" v-model="stockpoint">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label style="display: block;">&nbsp;</label>
                            <button class="btn btn-block btn-default" @click="addStockPoint">ADD</button>
                        </div>
                    </div>
                </div>
            </div>
			<div class="box box-widget">
				<div class="box-body" v-if="company">
					<div class="col-md-12">
                        <div class="table-responsive">
                            <table class="table table-bordered table-condensed">
                                <thead>
                                    <tr>
                                        <th>Stock Point</th>
                                        <th class="shrink" v-if="abilities.viewStockPointCustomers">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="stockpoint in stockPoints" :key="stockpoint.id">
                                        <td>{{stockpoint.name}}</td>
                                        <td v-if="abilities.viewStockPointCustomers">

                                            <router-link 
                                                :to="{name:'companies.locations.customers',params:{id:stockpoint.id}}"
                                                v-if="state.user.company"
                                            >
                                                Customers
                                            </router-link>
                                            <router-link 
                                                :to="{name:'admin.companies.locations.customers',params:{companyid:company.id,id:stockpoint.id}}"
                                                v-else
                                            >
                                                Customers
                                            </router-link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
				</div>
			</div>
		</section>
    </div>
</template>
<script>
    import Vue from 'vue'
    import {EventBus} from '../../configs/EventBus'
    import AppMixins from '../../vuex-mixin'
    import CompanySelect from '../companies/Select'
    import RegionSelect from '../region/Select'
    export default{
        mixins: [AppMixins],
        components:{
            CompanySelect,
            RegionSelect,
        },
        data(){
        	return {
        		company:null,
        		stockPoints:[],
                stockpoint:"",
                region:null,
        	}
        },
        mounted(){
        	if(this.state.user.company)
        		this.company = Object.assign({},this.state.user.company)
        },
        methods:{
            addStockPoint(){
                this.$validator.validateAll()
                .then(v => {
                    if(v)
                    {
                        EventBus.$emit('loader:show')
                        Vue.axios.post(`companies/${this.company.id}/stockpoints`,{stock_point:this.stockpoint})
                        .then(response => {
                            EventBus.$emit('loader:hide')
                            this.stockPoints.push(response.data)
                        })
                        .catch(error => {
                            if(error.response.status == 409)
                            {
                                swal({
                                    title: "Error",
                                    text: `Stock point ${this.stockpoint} already exists`,
                                    icon: 'error'
                                })
                            }
                        })
                    }
                })
            },
        	loadStockPoints(){
                EventBus.$emit('loader:show')
                Vue.axios.get(`companies/${this.company.id}/stockpoints`)
                .then(response =>{
                    EventBus.$emit('loader:hide')
                    this.stockPoints = response.data
                })
        	}
        },
        watch:{
        	'company.id':{
        		handler(n){
        			if(this.company)
        			{
        				this.loadStockPoints()
        			}
        		}
        	},
            'region.id':function(){
                this.company = null
            }
        }
    }
</script>