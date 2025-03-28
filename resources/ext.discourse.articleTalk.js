function getDiscourseBaseUrl() {
	return new URL(`${mw.config.get("DiscourseBaseUrl")}/`);
}

async function fetchHotPostsForTag(tag) {
	const hotPostsUrl = new URL(`tag/${tag}/l/hot.json`, getDiscourseBaseUrl());
	const response = await fetch(hotPostsUrl.toString());

	if (!response.ok) {
		throw new Error(`Failed to fetch hot posts for tag ${tag}`);
	}

	const data = await response.json();

	const posts = data.topic_list.topics.slice(0, 3).map(topicData => {
		const opId = topicData.posters[0].user_id;
		const author = data.users.find(user => user.id === opId);

		const linkUrl = new URL(`t/${topicData.slug}`, getDiscourseBaseUrl());

		const avatarUrl = new URL(author.avatar_template.replace("{size}", "64"), getDiscourseBaseUrl());

		return {
			id: topicData.id,
			title: topicData.fancy_title,
			url: linkUrl.toString(),
			excerpt: topicData.excerpt,
			authorName: author.name,
			authorAvatarUrl: avatarUrl.toString(),
			views: topicData.views,
			likes: topicData.like_count,
			comments: topicData.posts_count - 1,
		};
	});

	return posts;
}

function buildNewPostButton(tag) {
	const link = document.createElement("a");
	link.attributes['data-mw'] = "interface";
	link.href = new URL(`new-topic?tags=${tag}`, getDiscourseBaseUrl()).toString();

	link.innerHTML = `
		<span class="vector-icon mw-ui-icon-add-progressive mw-ui-icon-wikimedia-add-progressive"></span>
		<span>${mw.message('article-discourse-related-talk-new-post').escaped()}</span>
	`;

	return link;
}

function buildCard(postInfo) {
	const card = document.createElement("li");
	card.title = postInfo.title;

	card.innerHTML = `
		  <a href="${postInfo.url}">
				<span class="cdx-card">
					<span class="cdx-card__thumbnail cdx-thumbnail">
						${(postInfo.authorAvatarUrl) ? `<span class="cdx-thumbnail__image" style="background-image: url('${postInfo.authorAvatarUrl}')"></span>` : ``}
					</span>
					<span class="cdx-card__text">
						<span class="cdx-card__text__title">${postInfo.title}</span>
						<span class="cdx-card__text__description">${postInfo.excerpt}</span>
						<span class="cdx-card__text__supporting-text">
							<span>
								<span class="vector-icon discourse-icon mw-ui-icon-eye mw-ui-icon-wikimedia-eye"></span>
								${postInfo.views}
							</span>
							<span>
								<span class="vector-icon discourse-icon mw-ui-icon-heart mw-ui-icon-wikimedia-heart"></span>
								${postInfo.likes}
							</span>
							<span>
								<span class="vector-icon discourse-icon mw-ui-icon-speechBubble mw-ui-icon-wikimedia-speechBubble"></span>
								${postInfo.comments}
							</span>
						</span>
					</span>
				</span>
			</a>
  `;

	return card;
}

function buildPostsContainer(tag) {
	const container = document.createElement("div");
	container.className = "article-talk-container";

	container.innerHTML = `
		<aside class="noprint">
			<div class="article-talk-container-header-container">
				<h2 class="article-talk-container-header">${mw.message('article-discourse-related-talk-header').escaped()}</h2>
				${buildNewPostButton(tag).outerHTML}
			</div>
			<ul class="article-talk-container-card-list" id="article-talk-posts-list"></ul>
		</aside>
	`;

	return container;
}

const afterContentContainer = document.getElementById('mw-data-after-content');

if (!afterContentContainer) {
	return;
}

async function loadArticleTalk() {
	const pageTag = mw.config.get("DiscoursePageTag");
	if (!pageTag) {
		return;
	}

	const posts = await fetchHotPostsForTag(pageTag);

	if (posts.length === 0) {
		return;
	}

	const postsContainer = buildPostsContainer(pageTag);

	afterContentContainer.append(postsContainer);

	const postsList = document.getElementById("article-talk-posts-list");
	if (!postsList) {
		throw new Error("Somehow, the posts list wasn't provisioned.");
	}

	for (const post of posts) {
		const card = buildCard(post);

		postsList.append(card);
	}
}

$(() => {
	// The element is already in the viewport before JS ran.
	if ((document.documentElement.scrollHeight / 2) < document.documentElement.clientHeight) {
		loadArticleTalk().catch(err => console.error("Failed to load relevant article talk: ", err));
	} else {
		const observer = new IntersectionObserver(
			(entries) => {
				if (!entries[0].isIntersecting) {
					return;
				}
				observer.unobserve(afterContentContainer);
				observer.disconnect();
				loadArticleTalk().catch(err => console.error("Failed to load relevant article talk: ", err));
			}, {
			rootMargin: '-100% 0% 0% 0%'
		}
		);
		observer.observe(afterContentContainer);
	}
});
