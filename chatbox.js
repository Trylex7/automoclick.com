async function envoyerMessage(message) {
    const response = await fetch('/chatbot.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({message})
    });
    const data = await response.json();
    return data.response;
}
