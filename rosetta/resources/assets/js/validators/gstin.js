export default {
    getMessage(field, args){
        return `${field} is not valid person number`
    },
    validate(value,args){
    	let v = value.toUpperCase()
    	let state_codes = {
			'JK' : '01',
			'PUN' : '03',
			'CH' : '04',
			'CG' : '22',
			'ARP' : '12',
			'AS' : '18',
			'BIH' : '10',
			'GOA' : '30',
			'GUJ' : '24',
			'HR' : '06',
			'JHK' : '20',
			'KER' : '32',
			'MAN' : '14',
			'MP' : '23',
			'NAG' : '13',
			'ND' : '07',
			'ORI' : '21',
			'RAJ' : '08',
			'SIK' : '11',
			'TN' : '33',
			'UP' : '09',
			'WB' : '19',
			'KAR' : '29',
			'MAH' : '27',
			'UK' : '05',
			'PY' : '34',
			'MZ' : '15',
			'MR' : '16',
			'ML' : '17',
			'LD' : '31',
			'HP' : '02',
			'DH' : '26',
			'DD' : '25',
			'AN' : '35',
			'ANP' : '36',
			'AD' : '37',
    	}
    	if(args[0] == 'null')
    		return false
    	if(! /\d{2}[A-Z]{5}\d{4}[A-Z]{1}[A-Z\d]{1}[Z]{1}[A-Z\d]{1}/.test(v))
    	{
    		return false;
    	}
    	if(v.substr(0,2) == state_codes[args[0]])
    		return true
    	return false
    }
}