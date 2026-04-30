/**
 * Converts a subset of Markdown to safe HTML.
 * Handles: [text](url), **text**, `code`, and **.NET** style bold.
 */
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

    const title = document.createElement('strong');
    title.innerHTML = buildSignature(cmd.command, cmd.parameters);
    card.appendChild(title);

    const desc = document.createElement('p');
    let descHtml = renderMarkdown(cmd.description);
    if (cmd.dev) {
        descHtml += ' <span class="dev-badge">&#9888;&#xFE0F; In development &mdash; may not work as expected</span>';
    }
    desc.innerHTML = descHtml;
    card.appendChild(desc);

    return card;
}

async function loadCommands() {
    const grid = document.querySelector('.commands-grid');
    if (!grid) return;

    grid.innerHTML = '<p class="commands-loading">Loading commands&hellip;</p>';

    try {
        const response = await fetch('/api/v1/api-commands.php');
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const commands = await response.json();

        grid.innerHTML = '';
        for (const cmd of commands) {
            grid.appendChild(createCommandCard(cmd));
        }
    } catch (err) {
        grid.innerHTML = '<p class="commands-error">Failed to load commands. Please try again later.</p>';
        console.error('Commands fetch error:', err);
    }
}

document.addEventListener('DOMContentLoaded', loadCommands);
