/* =========================================
   MFANO BORA AFRICA RESOURCE CENTRE
========================================= */

/* =========================================
   MOBILE NAVIGATION
========================================= */

const mobileMenuButton = document.getElementById("mobileMenuButton");

const mainNavigation = document.querySelector(".main-navigation");

if (mobileMenuButton) {
  mobileMenuButton.addEventListener("click", function () {
    mainNavigation.classList.toggle("show");
  });
}

/* =========================================
   CLOSE MOBILE MENU WHEN LINK IS CLICKED
========================================= */

const navigationLinks = document.querySelectorAll(".main-navigation a");

navigationLinks.forEach(function (link) {
  link.addEventListener("click", function () {
    mainNavigation.classList.remove("show");
  });
});

/* =========================================
   RESOURCE SEARCH
========================================= */

const searchInput = document.getElementById("resourceSearch");

const categories = document.querySelectorAll(".category-card");

const noResults = document.getElementById("noResults");

if (searchInput) {
  searchInput.addEventListener("input", function () {
    const searchTerm = this.value.toLowerCase().trim();

    let visibleCategories = 0;

    categories.forEach(function (category) {
      const title = category.dataset.title || "";

      const description = category.dataset.description || "";

      const matches =
        title.includes(searchTerm) || description.includes(searchTerm);

      if (matches) {
        category.style.display = "flex";

        visibleCategories++;
      } else {
        category.style.display = "none";
      }
    });

    if (visibleCategories === 0) {
      noResults.style.display = "block";
    } else {
      noResults.style.display = "none";
    }
  });
}

/* =========================================
   CARD ANIMATION
========================================= */

const observer = new IntersectionObserver(
  function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add("visible");
      }
    });
  },
  {
    threshold: 0.1,
  },
);

categories.forEach(function (category) {
  observer.observe(category);
});
