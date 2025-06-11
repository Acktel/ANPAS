
      <ul>
        <li class="nav-item">
          <label for="idAnno" class="form-label">Scegli Anno di riferimento</label>
            <input
                type="number"
                name="idAnno"
                id="idAnno"
                class="form-control"
                min="2020"
                max="{{ date('Y') + 5 }}"
                step="1"
                value="{{ old('idAnno') }}"
                placeholder="Inserisci l'anno, es. 2024">
        </li>

      </ul>
            
      