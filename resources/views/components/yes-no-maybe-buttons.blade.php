<form class="pr-2" id="yes-form" action="{{ route('answers') }}" method="POST">
    @csrf
    <input name="question" type="hidden" value="{{ $quId }}">
    <input name="answer" type="hidden" value="yes">
    <button accesskey="y" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded" title="Press 1 to execute">Yes [1]</button>
</form>
<form id="no-form" action="{{ route('answers') }}" method="POST">
    @csrf
    <input name="question" type="hidden" value="{{ $quId }}">
    <input name="answer" type="hidden" value="no">
    <button accesskey="n" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded" title="Press 2 to execute">No [2]</button>
</form>
<form class="pl-2" id="skip-form" action="{{ route('answers') }}" method="POST">
    @csrf
    <input name="question" type="hidden" value="{{ $quId }}">
    <input name="answer" type="hidden" value="skip">
    <button accesskey="s" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded" title="Press 3 to execute">Skip [3]</button>
</form>

<script>
/**
 * Enhance form with keys	
 * 
 * Note this will fail in IE, but will not brake other code.
 */
class KeyboardActions {
	constructor() {
	}

	/** Init events. */
	init() {
		document.addEventListener('keypress', (evt) => {
			let charCode = evt.keyCode || evt.which;
			let character = String.fromCharCode(charCode);
		
			this.runAction(character);
		});
	}

	runAction(character) {
		const action = this.actionMapping(character);
		if (action === false) {
			return false;
		}
		const button = document.querySelector(`#${action}-form button`);
		if (!button) {
			return false;
		}
		button.dispatchEvent(new Event('click'));
		// console.log(character, action);
		return true;
	}
	
	/** @private */
	actionMapping(character) {
		let action = false;


		// Note, keep this mapping in sync with labels.
		switch (character) {
			case '1': action = 'yes'; break;
			case '2': action = 'no'; break;
			case '3': action = 'skip'; break;
		}
		return action;
	}
}

const keyboardActions = new KeyboardActions();
keyboardActions.init();

</script>