<template>
	<div class="row">
		<div class="col-md-10">
			<multiselect 
				v-model="engineer"
	      		:allow-empty="true" 
	      		:options="engineers" 
	      		label="id" 
	      		track-by="id" 
	      		placeholder="Select Installer" 
	      		:show-labels="false"
	      		:custom-label="customLabel"
	      		@select="engineerSelected" 
	      		@remove="engineerRemoved" 
	      		>
	      		<template slot="option" slot-scope="props">
	      			<div class="box-body engineer">
						<h1>{{props.option.name}}</h1>
					<!--	<h2>{{props.option.contact}}</h2>
						<p>{{props.option.address}}</p>
						<p>{{props.option.address_2}}</p>
						<p>{{props.option.city}} {{props.option.state.name}} {{props.option.post_code}}</p>
						<p>{{props.option.country_region_code}}</p>-->
						<p><abbr title="Phone">P:</abbr> {{props.option.phone_no}}</p>
					</div>
	    		</template>
	      	</multiselect>	
	    </div>
	    <div class="col-md-2">
	    	<button class="btn btn-default btn-block" @click="$refs.engineerModal.show()">ADD</button>
	    </div>
	    <create-engineer ref="engineerModal" :company="company" :distributor="distributor" :dealer="dealer" @engineer:added="engineerAdded"></create-engineer>
	</div>
</template>
<script>
	import Vue from 'vue'
	import Multiselect from 'vue-multiselect'
	import MultiselectCss from 'vue-multiselect/dist/vue-multiselect.min.css'
	import CreateEngineer from './Modal'
	export default{
		props:{
			company:{
				type:Object,
				required: true,
			},
			distributor:{
				type:Object,
				required:false,
			},
			dealer:
			{
				type:Object,
				required:false,
			}
		},
		components:{
			Multiselect,
			CreateEngineer,
		},
		data(){
			return {
				engineers:[],
				engineer:null,
			}
		},
		mounted(){
			let url = ""
		//	if(this.distributor)
		//	{
				url = `companies/${this.company.id}/distributors/${this.dealer.distributor_id}/engineers`
		/*	}
			if(this.dealer)
			{
				url = `companies/${this.company.id}/dealers/${this.dealer.id}/engineers`
			}*/
			Vue.axios.get(url)
			.then(response => {
				this.engineers = response.data
			})
		},
		methods:{
			engineerAdded(engineer){
				this.engineers.unshift(engineer)
				this.engineer = this.engineers[0]
				this.$emit('engineer:selected',engineer)
				this.$emit('input',engineer)
			},
			customLabel(engineer){
				return `${engineer.name} - ${engineer.phone_no}`
			},
			engineerSelected(engineer){
				this.$emit('engineer:selected',engineer)
				this.$emit('input',engineer)
			},
			engineerRemoved(){
				this.$emit('engineer:selected',null)
				this.$emit('input',null)
			}
		}
	}
</script>
<style>
.engineer{
	padding-bottom: 5px;
}
.engineer h1 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #666;
    font-size: 20px;
    font-weight: bold;

    /* text-align: center; */
}
.engineer h2 {
    margin-top: 0;
    /* margin-bottom: 0; */
    font-size: 18px;
}
.engineer p {
    margin-bottom: 5px;
}
</style>