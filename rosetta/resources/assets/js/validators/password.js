export default {
  getMessage(field, args){
        return `${field} must contain at least: 1 uppercase letter, 1 lowercase letter, 1 number, and one special character (E.g. , . _ & ? etc)`
    },
  validate (value) {

  	let strongRegex = new RegExp("^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{8,})");
    return strongRegex.test(value);

    /*return new Promise(resolve => {
      let phone = new PhoneNumber(value, 'IN');
      resolve({ valid: phone.isValid() })
    })*/
  }
}