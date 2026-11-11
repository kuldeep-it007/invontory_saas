<template>
	<div>
		<section class="content-header">
			<h1>Installer <span class="pull-right"><button class="btn btn-sm btn-default" @click="displayAddEngineerModal">ADD INSTALLER</button></span></h1>
		</section>
		<section class="content">
			<div class="box box-widget">
				<div class="box-body">
					<div class="col-md-6" v-for="(engineer,index) in engineers" :key="engineer.id">
						<div class="box box-default box-solid">
            				<div class="box-body engineer">
              					<h1>{{engineer.name}}</h1>
								<p><abbr title="Phone">P:</abbr> {{engineer.phone_no}}</p>
								<div class="actions">
									<a href="#" @click.prevent="editEngineer(index,engineer)"><i class="fa fa-pencil"></i></a>
									<a href="#" @click.prevent="deleteEngineer(index,engineer)"><i class="fa fa-times-circle"></i></a>
								</div>
            				</div>
          				</div>
					</div>
					<p v-if="loaded && !engineers.length"> No Engineers present</p>
				</div>
			</div>
		</section>
		<engineer-modal ref="engineerModal" :engineer="currentEngineer" :company="effectiveCompany" :distributor="effectiveDistributor" :dealer="effectiveDealer" :retailCustomer="effectiveRetailCustomer" @engineer:added="engineerAdded" @engineer:edited="engineerEdited"></engineer-modal>
	</div>
	
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	import EngineerModal from './Modal'
	export default{
		mixins : [appMixin],
		components:{
			EngineerModal
		},
		data(){
			return {
				engineers:[],
				loaded:false,
				currentEngineer:{
					id:null,
					phone_no: "",
					name: "",
				},
				effectiveCompany:{
					id:this.$route.params.id
				},
				effectiveDistributor:{
					id:null
				},
				effectiveDealer:{
					id:null
				},
				effectiveRetailCustomer:{
					id:null
				}
			}
		},
		mounted(){
			let url = ""
			/*if(this.$route.meta.type == 'distributor')
			{
				url = `companies/${this.$route.params.id}/distributors/${this.$route.params.distributorid}/engineers`
			}
			if(this.$route.meta.type == 'dealer')
			{
				url = `companies/${this.$route.params.id}/dealers/${this.$route.params.dealerid}/engineers`
			}
			if(this.$route.meta.type == 'retailCustomer')
			{
				url = `companies/${this.$route.params.id}/retailCustomers/${this.$route.params.retailCustomerId}/engineers`
			}*/
			url = `companies/${this.dealer.company.id}/distributors/${this.dealer.distributor_id}/engineers`
			EventBus.$emit('loader:show')
			Vue.axios.get(url)
			.then(response => {
				EventBus.$emit('loader:hide')
				this.loaded = true
				this.engineers = response.data
			})
			if(this.$route.params.distributorid)
			{
				this.effectiveDistributor.id = this.$route.params.distributorid
			}
			if(this.$route.params.dealerid)
			{
				this.effectiveDealer.id = this.$route.params.dealerid
			}
			if(this.$route.params.retailCustomerId)
			{
				this.effectiveRetailCustomer.id = this.$route.params.retailCustomerId
			}
		},
		methods:{
			deleteEngineer(index,engineer){
				swal({
                    title: "Delete Installer?",
                    icon:'warning',
                    buttons: {
                        cancel: true,
                        confirm: true,
                    }
                })
                .then(value => {
                    if(value !== null)
                    {
                    	let url = ""
						if(this.$route.meta.type == 'distributor')
						{
							url = `companies/${this.$route.params.id}/distributors/${this.$route.params.distributorid}/engineers/${encodeURIComponent(engineer.id)}`
						}
						if(this.$route.meta.type == 'dealer')
						{
							url = `companies/${this.$route.params.id}/dealers/${this.$route.params.dealerid}/engineers/${encodeURIComponent(engineer.id)}`
						}
						if(this.$route.meta.type == 'retailCustomer')
						{
							url = `companies/${this.$route.params.id}/retailCustomers/${this.$route.params.retailCustomerId}/engineers/${encodeURIComponent(engineer.id)}`
						}
                    	EventBus.$emit('loader:show')
                    	Vue.axios.delete(url)
                    	.then(response => {
                    		EventBus.$emit('loader:hide')
              				this.engineers.splice(index,1)      		
                    	})
                    	.catch(error=>{
                    		EventBus.$emit('loader:hide')
                    	})
                    }
                })
                .catch()
				//
			},
			editEngineer(index,engineer){
				let self = this
				this.currentEngineer = Object.assign({},engineer)
				Vue.nextTick(function(){
					self.$refs.engineerModal.show()
				})
			},
			displayAddEngineerModal(){
				this.$refs.engineerModal.show()
			},
			engineerEdited(engineer){
				let index = _.findIndex(this.engineers,function(element){
					return element.id == engineer.id
				})
				console.log('index is',index)
				this.engineers.splice(index,1,engineer)
			},
			engineerAdded(engineer)
			{
				this.engineers.unshift(engineer)
			}
		}
	}
</script>
<style scoped>
.engineer{
	padding-bottom: 5px;
}
.engineer h1 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #666;
    font-size: 20px;
    font-weight: bold;
}
.engineer h2 {
    margin-top: 0;
    font-size: 18px;
}
.engineer p {
    margin-bottom: 5px;
}
.actions{
	position: absolute;
    top: 0;
    right: 5px;
    color:#666;
}
.actions a{
	color:#666;
}
</style>