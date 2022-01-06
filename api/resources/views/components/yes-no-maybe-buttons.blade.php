<form class="pr-2" id="yes-form" action="{{ route('answers') }}" method="POST">
    @csrf
    <input name="question" type="hidden" value="{{ $quId }}">
    <input name="answer" type="hidden" value="yes">
    <button class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">Yes</button>
</form>
<form id="no-form" action="{{ route('answers') }}" method="POST">
    @csrf
    <input name="question" type="hidden" value="{{ $quId }}">
    <input name="answer" type="hidden" value="no">
    <button class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">No</button>
</form>
<form class="pl-2" id="skip-form" action="{{ route('answers') }}" method="POST">
    @csrf
    <input name="question" type="hidden" value="{{ $quId }}">
    <input name="answer" type="hidden" value="skip">
    <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Skip</button>
</form>
