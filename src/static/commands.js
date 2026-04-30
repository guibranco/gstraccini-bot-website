function renderMarkdown(text) {
    return text
        .replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/`([^`]+)`/g, '<code>$1</code>');
}

function buildSignature(command, parameters) {
    let sig = '@gstraccini ' + command;
    if (Array.isArray(parameters)) {
        for (const p of parameters) {
            sig += p.required ? ' &lt;' + p.parameter + '&gt;' : ' [' + p.parameter + ']';
        }
    }
    return sig;
}

function createCommandCard(cmd) {
    const card = document.createElement('div');
    card.className = 'command-card';
    card.dataset.command = cmd.command.toLowerCase();

    const header = document.createElement('div');
    header.className = 'command-header';

    const sig = document.createElement('code');
    sig.className = 'command-sig';
    sig.innerHTML = buildSignature(cmd.command, cmd.parameters);
    header.appendChild(sig);

    const badges = document.createElement('div');
    badges.className = 'command-badges';
    if (cmd.dev) {
        const b = document.createElement('span');
        b.className = 'badge badge-dev';
        b.title = 'In development — may not work as expected';
        b.textContent = 'In Development';
        badges.appendChild(b);
    }
    if (cmd.requiresPullRequestOpen) {
        const b = document.createElement('span');
        b.className = 'badge badge-pr';
        b.title = 'Requires an open pull request';
        b.textContent = 'Requires PR';
        badges.appendChild(b);
    }
    if (badges.hasChildNodes()) header.appendChild(badges);
    card.appendChild(header);

    const desc = document.createElement('p');
    desc.className = 'command-desc';
    desc.innerHTML = renderMarkdown(cmd.description);
    card.appendChild(desc);

    if (Array.isArray(cmd.parameters) && cmd.parameters.length > 0) {
        const list = document.createElement('ul');
        list.className = 'param-list';
        for (const p of cmd.parameters) {
            const li = document.createElement('li');

            const name = document.createElement('code');
            name.className = 'param-name';
            name.textContent = p.parameter;
            li.appendChild(name);

            const req = document.createElement('span');
            req.className = p.required ? 'param-required' : 'param-optional';
            req.textContent = p.required ? 'required' : 'optional';
            li.appendChild(req);

            li.appendChild(document.createTextNode(' — ' + p.description));
            list.appendChild(li);
        }
        card.appendChild(list);
    }

    return card;
}

function updateCount(visible, total) {
    const el = document.getElementById('commands-count');
    if (el) el.textContent = visible === total ? total + ' commands' : visible + ' of ' + total + ' commands';
}

async function loadCommands() {
    const grid = document.querySelector('.commands-grid');
    if (!grid) return;

    grid.innerHTML = '<p class="commands-loading">Loading commands…</p>';

    let commands = [];
    try {
        const res = await fetch('/api/v1/api-commands.php');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        commands = await res.json();
    } catch (err) {
        grid.innerHTML = '<p class="commands-error">Failed to load commands. Please try again later.</p>';
        console.error('Commands fetch error:', err);
        return;
    }

    grid.innerHTML = '';
    for (const cmd of commands) grid.appendChild(createCommandCard(cmd));
    updateCount(commands.length, commands.length);

    const search = document.getElementById('commands-search');
    if (!search) return;

    search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        const cards = grid.querySelectorAll('.command-card');
        let visible = 0;

        cards.forEach(card => {
            const match = !q
                || card.dataset.command.includes(q)
                || card.querySelector('.command-desc')?.textContent.toLowerCase().includes(q);
            card.hidden = !match;
            if (match) visible++;
        });

        let noResults = grid.querySelector('.commands-no-results');
        if (visible === 0) {
            if (!noResults) {
                noResults = document.createElement('p');
                noResults.className = 'commands-no-results';
                grid.appendChild(noResults);
            }
            noResults.textContent = 'No commands match “' + search.value + '”.';
        } else {
            noResults?.remove();
        }

        updateCount(visible, commands.length);
    });
}

document.addEventListener('DOMContentLoaded', loadCommands);
