document.addEventListener('DOMContentLoaded', function() {
    // Username Persistence
    const nameInputs = document.querySelectorAll('input[name="name"]');
    const savedName = localStorage.getItem('piratebox_username');

    if (savedName) {
        nameInputs.forEach(input => input.value = savedName);
    }

    nameInputs.forEach(input => {
        input.addEventListener('input', (e) => localStorage.setItem('piratebox_username', e.target.value));
    });

    // Navbar toggle
    const toggler = document.querySelector('.navbar-toggler');
    const menu = document.querySelector('.navbar-menu');
    if (toggler && menu) {
        toggler.addEventListener('click', () => {
            menu.classList.toggle('active');
        });
    }

    // Upload Form Logic (index.php)
    // We select by enctype to specifically target the file upload form
    const uploadForm = document.querySelector('form[enctype="multipart/form-data"]');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            const btn = uploadForm.querySelector('button');
            const fileLabel = uploadForm.querySelector('label');
            const fileInput = uploadForm.querySelector('input[name="file"]');

            if (fileInput && fileInput.files.length > 0) {
                // Get max size from data attribute
                const maxSize = parseInt(fileInput.getAttribute('data-max-size'), 10);
                
                if (!isNaN(maxSize) && fileInput.files[0].size > maxSize) {
                    e.preventDefault();
                    alert('File is too large. Maximum size is ' + (maxSize / 1024 / 1024) + 'MiB.');
                    return;
                }
            }

            btn.disabled = true;
            btn.textContent = 'UPLOADING...';
            btn.classList.add('upload-animation');
            if (fileInput) {
                fileInput.style.pointerEvents = 'none';
                fileInput.style.opacity = '0.5';
                fileInput.style.display = 'none';
                fileLabel.style.display = 'none';
            }
        });
    }

    // File Search Logic (index.php)
    const searchInput = document.getElementById('fileSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('table tbody tr');

            rows.forEach(row => {
                // Filter only if 3 or more characters, otherwise show all
                if (filter.length >= 3 && !row.textContent.toLowerCase().includes(filter)) {
                    row.style.display = "none";
                } else {
                    row.style.display = "";
                }
            });
        });
    }

    // Message Character Count Logic (messages.php)
    const messageInput = document.querySelector('textarea[name="message"]');
    const charCountDisplay = document.getElementById('char-count');

    if (messageInput && charCountDisplay) {
        const maxLength = messageInput.getAttribute('maxlength');
        messageInput.addEventListener('input', function() {
            charCountDisplay.textContent = `${messageInput.value.length} / ${maxLength}`;
        });
    }

    // Message Time Formatting (messages.php)
    document.querySelectorAll('.message-time[data-timestamp]').forEach(el => {
        const date = new Date(parseInt(el.dataset.timestamp) * 1000);
        el.textContent = Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(date);
    });

    // Chat Time Formatting (chat.php)
    document.querySelectorAll('.chat-timestamp[data-timestamp]').forEach(el => {
        const date = new Date(parseInt(el.dataset.timestamp) * 1000);
        el.textContent = Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(date);
    });

    // File Time Formatting (index.php)
    document.querySelectorAll('.file-timestamp[data-timestamp]').forEach(el => {
        const date = new Date(parseInt(el.dataset.timestamp) * 1000);
        el.textContent = Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(date);
    });

    // Chat Logic (chat.php)
    const chatList = document.getElementById('chat');
    const chatForm = document.getElementById('chat-form');

    if (chatList && chatForm) {
        // Chat Character Counter
        const chatInput = chatForm.querySelector('input[name="content"]');
        const chatCharCount = document.getElementById('char-count');
        
        if (chatInput && chatCharCount) {
            const maxLength = chatInput.getAttribute('maxlength');
            chatInput.addEventListener('input', () => {
                chatCharCount.textContent = `${chatInput.value.length} / ${maxLength}`;
            });
        }

        chatForm.addEventListener('submit', async event => {
            const form = event.target;
            let name = form.name.value;
            const content = form.content.value;
            const csrf_token = form.csrf_token.value;

            event.preventDefault();

            if (name == "")
                name = "Anonymous";

            if (content == "")
                  return;

            await fetch(form.action, { method: "POST", body: new URLSearchParams({ name, content, csrf_token }) });
            
            const template = chatList.querySelector("template");
            if (template) {
                const messageElement = template.content.cloneNode(true);
                const small = messageElement.querySelector("small");
                const contentSpan = messageElement.querySelector("span");

                small.textContent = "";
                const nameSpan = document.createElement("span");
                nameSpan.className = "chat-name";
                nameSpan.textContent = name;
                small.appendChild(nameSpan);
                small.appendChild(document.createTextNode(" ("));
                const timeSpan = document.createElement("span");
                timeSpan.className = "chat-timestamp";
                timeSpan.textContent = Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(new Date());
                small.appendChild(timeSpan);
                small.appendChild(document.createTextNode("): "));
                contentSpan.textContent = content;
                chatList.append(messageElement);
                chatList.scrollTop = chatList.scrollHeight;
            }

            form.content.value = "";
            form.content.focus();
            
            if (chatCharCount && chatInput) {
                chatCharCount.textContent = `0 / ${chatInput.getAttribute('maxlength')}`;
            }
        });

        async function poll_for_new_chat() {
            try {
                const response = await fetch("chat.php?fetch=1", { cache: "no-cache" });
                if (!response.ok) return;

                const chat = await response.json();
                const template = chatList.querySelector("template");
                if (!template) return;
                const messageTemplate = template.content.querySelector("li");

                const pixelDistanceFromListeBottom = chatList.scrollHeight - chatList.scrollTop - chatList.clientHeight;
                const scrollToBottom = (pixelDistanceFromListeBottom < 50);

                chatList.querySelectorAll("li.pending").forEach(li => li.remove());

                const lastMessageId = parseInt(chatList.dataset.lastMessageId ?? "-1");

                for (const msg of chat) {
                    if (msg.id > lastMessageId) {
                        const date = new Date(msg.time * 1000);
                        const messageElement = messageTemplate.cloneNode(true);
                        messageElement.classList.remove("pending");
                        const small = messageElement.querySelector("small");
                        const contentSpan = messageElement.querySelector("span");

                        small.textContent = "";
                        const nameSpan = document.createElement("span");
                        nameSpan.className = "chat-name";
                        nameSpan.textContent = msg.name;
                        small.appendChild(nameSpan);
                        small.appendChild(document.createTextNode(" ("));
                        const timeSpan = document.createElement("span");
                        timeSpan.className = "chat-timestamp";
                        timeSpan.textContent = Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(date);
                        small.appendChild(timeSpan);
                        small.appendChild(document.createTextNode("): "));
                        contentSpan.textContent = msg.content;
                        chatList.append(messageElement);
                        chatList.dataset.lastMessageId = msg.id;
                    }
                }

                Array.from(chatList.querySelectorAll("li")).slice(0, -1000).forEach(li => li.remove());

                if (scrollToBottom)
                    chatList.scrollTop = chatList.scrollHeight - chatList.clientHeight;
            } catch (e) {
                console.error("Chat polling error", e);
            }
        }

        poll_for_new_chat();
        setInterval(poll_for_new_chat, 1000);
    }
});