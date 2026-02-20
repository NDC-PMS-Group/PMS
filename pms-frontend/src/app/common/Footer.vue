<template>
  <div class="font-sans bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-300 border-t border-slate-200 dark:border-slate-800">
    <div class="py-10 max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-3 gap-10">
      
      <!-- Left Column -->
      <div>
        <div class="flex items-start mb-6">
          <img 
            src="https://cda.gov.ph/wp-content/uploads/2020/12/rp.png" 
            alt="Republic of the Philippines" 
            class="w-32 h-auto mr-4 opacity-60"
          />
          <div>
            <h3 class="font-semibold uppercase tracking-wide text-sm mb-1">
              Republic of the Philippines
            </h3>
            <p class="text-sm">
              All content is in the public domain unless otherwise stated.
            </p>
          </div>
        </div>

        <div class="flex items-start">
          <img 
            src="https://cda.gov.ph/wp-content/uploads/2020/12/foi-1.png" 
            alt="Freedom of Information" 
            class="w-32 h-auto mr-4 opacity-60"
          />
          <div>
            <h3 class="font-semibold uppercase tracking-wide text-sm mb-1">
              Freedom of Information
            </h3>
            <a
              href="https://www.foi.gov.ph/" 
              target="_blank" 
              rel="noopener noreferrer" 
              class="text-sm text-slate-600 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400"
            >
              Learn more about the Executive Order No. 2 - The order implementing 
              Freedom of Information in the Philippines.
            </a>
          </div>
        </div>
      </div>

      <!-- Middle Column -->
      <div>
        <h3 class="font-semibold uppercase tracking-wide text-sm mb-3">About GOVPH</h3>
        <p class="text-sm mb-4">
          Learn more about the Philippine government, its structure, how government works 
          and the people behind it.
        </p>
        <ul class="space-y-2 text-sm">
          <li>
            <a href="http://www.gov.ph/" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400">GOV.PH</a>
          </li>
          <li>
            <a href="http://www.gov.ph/data" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400">Open Data Portal</a>
          </li>
          <li>
            <a href="http://www.officialgazette.gov.ph/" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400">Official Gazette</a>
          </li>
        </ul>
      </div>

      <!-- Right Column -->
      <div>
        <h3 class="font-semibold uppercase tracking-wide text-sm mb-3">Government Links</h3>
        <ul class="space-y-2 text-sm leading-4">
          <li><a href="http://president.gov.ph/" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400">Office of the President</a></li>
          <li><a href="http://ovp.gov.ph/" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400">Office of the Vice President</a></li>
          <li><a href="http://www.senate.gov.ph/" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400">Senate of the Philippines</a></li>
          <li><a href="http://www.congress.gov.ph/" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400">House of Representatives</a></li>
          <li><a href="http://sc.judiciary.gov.ph/" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400">Supreme Court</a></li>
          <li><a href="http://ca.judiciary.gov.ph/" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400">Court of Appeals</a></li>
          <li><a href="http://sb.judiciary.gov.ph/" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400">Sandiganbayan</a></li>
        </ul>
      </div>
      
    </div>

    <div class="flex justify-center items-center py-6 bg-slate-200 dark:bg-slate-950/50 border-t border-slate-300 dark:border-slate-800">
        <p class="text-center text-sm text-slate-500 dark:text-slate-400">
            © 2021-2025 Cooperative Development Authority All rights reserved.
        </p>
    </div>
  </div>
</template>
