/**
 * Chèn 2 nút bấm bên dưới tiêu đề Blog (h1.wp-block-heading).
 */
add_action(
	'wp_footer',
	function () {
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			const blogHeading = document.querySelector('h1.wp-block-heading');
			if (!blogHeading) {
				return;
			}

			const btnGroup = document.createElement('div');
			btnGroup.style.marginTop = '15px';
			btnGroup.style.marginBottom = '15px';
			btnGroup.style.display = 'flex';
			btnGroup.style.gap = '10px';

			const btnHello = document.createElement('button');
			btnHello.innerText = 'Bấm thông báo';
			Object.assign(btnHello.style, {
				padding: '8px 16px',
				backgroundColor: '#0073aa',
				color: '#ffffff',
				border: 'none',
				borderRadius: '4px',
				cursor: 'pointer',
				fontSize: '14px'
			});
			btnHello.addEventListener('click', function () {
				alert('Hello World!');
			});

			const btnColor = document.createElement('button');
			btnColor.innerText = 'Đổi màu chữ Blog';
			Object.assign(btnColor.style, {
				padding: '8px 16px',
				backgroundColor: '#23282d',
				color: '#ffffff',
				border: 'none',
				borderRadius: '4px',
				cursor: 'pointer',
				fontSize: '14px'
			});

			const colors = ['red', '#22c55e', '#3b82f6', '#eab308', '#ec4899'];
			let colorIndex = 0;
			btnColor.addEventListener('click', function () {
				blogHeading.style.color = colors[colorIndex];
				colorIndex = (colorIndex + 1) % colors.length;
			});

			btnGroup.appendChild(btnHello);
			btnGroup.appendChild(btnColor);
			blogHeading.after(btnGroup);
		});
		</script>
		<?php
	}
);
