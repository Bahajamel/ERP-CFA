@php($champ = 'border:1px solid rgb(203 213 225);border-radius:.5rem;padding:.5rem .75rem;font-size:.85rem;flex:1;color:#0f172a;background:#fff;')

<div style="display:flex;flex-direction:column;gap:.35rem;" x-data>
    <label style="font-size:.8rem;font-weight:600;color:#334155;">Lien public à partager</label>
    <div style="display:flex;gap:.5rem;">
        <input type="text" readonly value="{{ $lien }}" onclick="this.select()" style="{{ $champ }}">
        <button type="button"
            style="border:0;border-radius:.5rem;padding:.5rem .9rem;font-size:.85rem;font-weight:600;cursor:pointer;color:#fff;white-space:nowrap;background:#4f46e5;"
            x-on:click="navigator.clipboard.writeText('{{ $lien }}'); $el.textContent = 'Copié ✓'; setTimeout(() => $el.textContent = 'Copier', 1500)">
            Copier
        </button>
    </div>
    <p style="font-size:.75rem;color:#64748b;">Copiez ce lien et envoyez-le au candidat par le canal de votre choix.</p>
</div>
