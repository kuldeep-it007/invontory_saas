<template>
	<div v-if="abilities.sendNotification">
		<section class="content-header">
			<h1>Send Notification
				 <span class="pull-right"><button class="btn btn-sm btn-primary" @click="send()" :disabled=' !(sendTo) || !(notification_title) || !(notification_text) || (sendTo == "selectedCustomers" && finalSelectedCustomers.length <= 0)|| notification_text.length >500' >Send</button></span>
			</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-6">
					<div class="box box-widget">
						<div class="box-body">
								<div class="col-md-12" v-if="this.state.user.company == null">
									<div class="form-group">
										<label>Region</label>
										<region-select v-model="region"></region-select>
									</div>
								</div>
								<div class="col-md-12" v-if="this.state.user.company == null">
									<div class="form-group">
										<label>Company</label>
										<company v-model="company" :region="region" :disabled="!region"></company>
									</div>
								</div>
							<div>
								<div class="col-md-12" v-if="company">
									<div class="form-group">
										<h4>Select Customers</h4>
										<input type="radio" v-model="sendTo" value="allCustomers">  All Customers &nbsp;&nbsp;
										<input type="radio" v-model="sendTo" value="allDistributors"> All Distributors &nbsp;&nbsp;
										<input type="radio" v-model="sendTo" value="allDealers"> All Dealers &nbsp;&nbsp;
										<input type="radio" v-model="sendTo" value="selectedCustomers"> Selected Customers 
									</div>
								</div>
							</div>
							<div class="col-md-12" v-if="sendTo == 'selectedCustomers'">
								<div class="form-group">
		                        	<label>Customer Type</label>
		                        	<customer-type v-model="customer_type" :disabled="!company"></customer-type>
		                    	</div>
			                </div>
			                <div class="col-md-12" v-if="sendTo == 'selectedCustomers'">
		                	    <div class="form-group" :class="errors.has('selectedCustomers') ? 'has-error' : ''">
			                        <label>
			                            <template v-if="customer_type">
			                                <template v-if="customer_type.code == 'DISTRIBUTO'">Distributor</template>
			                                <template v-if="customer_type.code == 'DEALER'">Dealer</template>
			                            </template>
			                            <template v-else>   
			                                Customer
			                            </template>
			                        </label>
			                        <customer-multi-select v-model="selectedCustomers" :company="company" :customer_type="customer_type" :disabled="!company" :required="true" v-validate="'required'" data-vv-name="selectedCustomers" :options="CustomerMultiSelect" :multiple="true" @input="onCustomerSelect"></customer-multi-select>
			                    </div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="box box-widget">
						<div class="box-body">
							<div class="form-group">
								<label>Title</label>
								<input type="text" class="form-control" v-model="notification_title" v-validate="'max:230'" data-vv-name="notification_title">
								 <span class="text-danger" v-show="errors.has('notification_title')">Notification Title can not exceed 230 characters.</span>
							</div>
							<div class="form-group">
								<label>message</label>
								<textarea class="form-control" v-model="notification_text" v-validate="'max:500'" data-vv-name="notification_text" rows="8"></textarea>
								 <span class="text-danger" v-show="errors.has('notification_text')">Notification Text can not exceed 500 characters.</span>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row" v-if="sendTo == 'selectedCustomers' && finalSelectedCustomers.length">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body table-responsive">
							<table class="table table-condensed table-striped table-bordered">
                				<thead>
                    				<tr>
                        				<th>Customer Name</th>
                        				<th class="shrink">Customer Number</th>
                        				<th class="shrink">Customer Type</th>
                        				<th class="shrink">&nbsp;</th>
                    				</tr>
                				</thead>
                				<tbody>
                    				<tr v-for="(finalSelectedCustomer,index) in finalSelectedCustomers">
                        				<td>{{finalSelectedCustomer.name}}</td>
                        				<td class="shrink">{{finalSelectedCustomer.nav_id}}</td>
                        				<td class="shrink">{{finalSelectedCustomer.type}}</td>
                        				<td>
                        					<a href="#" @click.prevent="deleteCustomer(index)">
                        						<i class="fa fa-times"></i>
                        					</a>
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
    import appMixin from '../../vuex-mixin.js'
    import RegionSelect from '../region/Select'
	import Company from '../companies/Select'
    import CustomerType from '../master/CustomerType'
   	import CustomerMultiSelect from '../master/CustomerMultiSelect'
    import Multiselect from 'vue-multiselect'
    export default{
		mixins : [appMixin],
		components:{
			RegionSelect,
			Company,
			CustomerMultiSelect,
            CustomerType,
            Multiselect,
		},
		data(){
            return {
                notification_text:null,
                notification_title:null,
                sendTo:null,
                region:null,
				company:null,
				selectedCustomers:[],
				finalSelectedCustomers:[],
			    customer_type:{code:'DISTRIBUTO',name:"Distributor"},
                CustomerMultiSelect,
            }
        },
        mounted(){
			let self = this
			if(this.state.user.company)
			{
				this.company = 	Object.assign({},this.state.user.company)
			}
			if(!(this.isCustomer) && !(this.state.user.company))
				this.company = null
		},
		 methods:{
		 	deleteCustomer(index)
		 	{
		 		this.finalSelectedCustomers.splice(index,1)
		 	},
			onCustomerSelect(selected){
                this.finalSelectedCustomers.push(selected)
            },
            send(){
            	let self = this
            	let input = {
            		sendTo : self.sendTo,
            		selectedCustomers : self.finalSelectedCustomers,
            		notification_title : self.notification_title,
            		notification_text : self.notification_text
            	}
            	let url = `companies/${this.company.id}/notification`
            	Vue.axios.post(url, input)
                .then(response => {
                    swal({
                        title:"Notification sent!",
                        icon:"success"
                    })
                    .then(value => {
                        
                    }) 
                })
            }
		},
		watch:{
			'sendTo':function(value){
				if(value != "selectedCustomers")
				{
					this.finalSelectedCustomers = []
					return
				}
			},
			'company.id':function(){
                this.selectedCustomer = null
            },
            'region.id':function(){
                this.company = null
            }
		}
	}
</script>