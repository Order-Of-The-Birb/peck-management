import discord, logging, re, asyncio
from discord.ext import commands
from typing import TYPE_CHECKING
from datetime import datetime, UTC
from psutil import virtual_memory, Process as psutilProcess
from psutil._common import bytes2human
if __name__ == "__main__":
	from os import path
	from sys import path as sys_path
	sys_path.append(path.abspath(path.join(path.dirname(__file__), '..')))
if TYPE_CHECKING:
	from utils.bot import Bot
from utils.bot import debug_only
import utils.wt as wtUtil
import utils.generic as genericUtil 
__reload_deps__ = ("utils.wt", "utils.generic")

def toSelectList(names:list[str]) -> list[discord.SelectOption]: return [discord.SelectOption(label=name) for name in names]
class NormalCog(commands.Cog):
	def __init__(self, bot:'Bot'):
		self.bot = bot
		self._logger = logging.getLogger(__name__)
		self._logger.setLevel(bot.logLevel)
		self._logger.debug("Normal Commands initialized")
	# region Ungrouped
	@discord.app_commands.command()
	@discord.app_commands.guild_only()
	async def apply(self, interaction: discord.Interaction):
		"""This Command is used for applying to the squadron"""
		class join_modal(discord.ui.Modal, title="Submit application"):
			username = discord.ui.Label(
				text = "War Thunder username",
				description = "Your War Thunder username",
				component = discord.ui.TextInput(
					style = discord.TextStyle.short,
					custom_id = "joinWTUsername",
					min_length = 2,
					max_length = 16,
					placeholder = "username"
				)
			)
			tz = discord.ui.Label(
				text = "Timezone", 
				description = "Please provide your timezone's UTC offset",
				component = discord.ui.Select(
					options = toSelectList([str(i) for i in range(-11, 13)]), 
					placeholder="I'd rather not provide",
					row = 1, 
					required = False,
					min_values = 1,
					max_values = 1
				)
			)
			def __init__(self, parent:'NormalCog'):
				self.parent = parent
				super().__init__()
			async def on_submit(self, interaction: discord.Interaction):
				await interaction.response.defer(thinking=True, ephemeral=True)
				await self.parent.bot.squadron.updateMembers()
				# region Username sanitation
				USERNAME_REGEX = re.compile(r"^([A-Za-z0-9_ ]{2,16})(?:@(live|psn))?$")
				username = USERNAME_REGEX.search(self.username.component.value.strip())
				if username: username = username.group(1)
				else:
					await interaction.edit_original_response(content=f"Given username is invalid. The usernames follow the following rules:\n- Between 2 and 16 characters\n- Can have latin letters, numbers\n- Can contain spaces in the middle")
					return
				# endregion
				# region User already applied/registered
				userInfo = self.parent.bot.db.getByName(username)
				if userInfo is not None:
					if userInfo.status == self.parent.bot.db.Status.MEMBER or interaction.user.id != userInfo.discord_id:
						await self.parent.bot.get_channel(self.parent.bot.spamChID).send(f"{interaction.user.name} ({interaction.user.id}) tried joining with the username \"{username}\", but this username is already registered as a member.")
						await interaction.edit_original_response(content="User is already registered in the squadron. Possible false name detected.")
						return
					if userInfo.status == self.parent.bot.db.Status.APPLICANT:
						await interaction.edit_original_response(content="You have already applied before! Please wait for an admin to accept you.")
						return
				# endregion
				# region Add new user to the database
				joindate = None
				for member in self.parent.bot.squadron.members:
					if member.name == username:
						joindate = member.joindate.date()
						break
				gaijin_id = wtUtil.get_user_ids(username)[username]
				try:
					self.parent.bot.db.add_user(gaijin_id=gaijin_id, username=username, discord_id=interaction.user.id, joindate=joindate, timezone=int(self.tz.component.values[0]) if len(self.tz.component.values) != 0 else None, status=self.parent.bot.db.Status.APPLICANT)
				except ValueError:
					self.parent._logger.exception("An exception occurred while adding applicant to the database")
					await interaction.edit_original_response(content="You could not be added to the database")
					return
				# endregion
				# region User already accepted
				embed = discord.Embed(color=0xFF0000, title="Application")
				if self.parent.bot.squadron.UserInSquadron(username):
					_ = self.parent.bot.db.getByName(username)
					_.status = self.parent.bot.db.Status.MEMBER
					_.push()
					embed.title = "User verified"
					memberRole = self.parent.bot.get_guild(self.parent.bot.peckServer).get_role(self.parent.bot.memberRoleID)
					await interaction.user.add_roles(memberRole)
					for roleID in self.parent.bot.commonRoles:
						commonRole = self.parent.bot.get_guild(self.parent.bot.peckServer).get_role(roleID)
						await interaction.user.remove_roles(commonRole)
				# endregion
				await interaction.edit_original_response(content="You have been successfully added to the database.")
				# region Logging
				try:
					channel = self.parent.bot.get_channel(self.parent.bot.spamChID)
					embed.set_author(name=f"{interaction.user.name} ({interaction.user.id})", icon_url=interaction.user.display_avatar.url)
					embed.add_field(name="DC Username", value=interaction.user.name)
					embed.add_field(name="Discord UID", value=interaction.user.id)
					embed.add_field(name="WT Username", value=username)
					await channel.send(embed=embed)
				except Exception:
					self.parent._logger.exception("An error occurred while trying to log user joining.")
				# endregion
			async def on_error(self, interaction: discord.Interaction, error: Exception) -> None:
				self.parent._logger.exception(f"An error occured in 'Apply'", exc_info=True)
				if interaction.response.is_done():
					await interaction.edit_original_response(content='Oops! Something went wrong.')
				else:
					await interaction.response.send_message('Oops! Something went wrong.', ephemeral=True)
		if self.bot.db.getByDID(interaction.user.id):
			await interaction.response.send_message("You are already a registered member. If you wish to add an alt account, consult an officer.")
			return
		await interaction.response.send_modal(join_modal(self))

	@discord.app_commands.command()
	async def current_br(self, interaction:discord.Interaction):
		await interaction.response.defer()
		tmp = wtUtil.sqb_br(True)
		if tmp is None:
			await interaction.edit_original_response(content=f"Could not get current BR data. Please try again later...")
			self._logger.error(f"sqb_br returned '{tmp}'")
			return
		await interaction.edit_original_response(content=f"Current BR:\n# {tmp[0]}\nCurrent BR started {tmp[1][0]} and ends {tmp[1][1]}")

	@discord.app_commands.command(description="Command to report a squadron member")
	@discord.app_commands.guild_only()
	async def report(self, interaction: discord.Interaction):
		class ReportModal(discord.ui.Modal, title="Report user"):
			user = discord.ui.Label(
				text="War Thunder Username",
				component=discord.ui.TextInput(
					min_length=2,
					max_length=16,
					required=True,
					style=discord.TextStyle.short,
					custom_id="report_username",
					placeholder="username"
				),
				description="The War Thunder username of the player. If they are on console adding the @psn or @live tags is optional"
			)
			type = discord.ui.Label(
				text="Category",
				component=discord.ui.Select(
					min_values=1,
					max_values=1,
					options=[
						discord.SelectOption(label="Cheating", value="cheating", description="Aimbot, ESP/Wallhacks, Botted gameplay, etc."),
						discord.SelectOption(label="Toxicity", value="chat"),
						discord.SelectOption(label="Annoying", value="annoying", description="If your only problem is this user being annoying"),
						discord.SelectOption(label="Sabotage", value="sabotage", description="Teamkilling, stealing air slots, etc.")
					],
					required=True,
					custom_id="report_category",
					id=69
				),
				description="The category the user's infraction would fall under"
			)
			message = discord.ui.Label(
				text="Message",
				component=discord.ui.TextInput(
					min_length=1, 
					max_length=2048,
					custom_id="report_msg",
					style=discord.TextStyle.paragraph,
					placeholder="I would like to report...",
					required=False
				),
				description="The message you wish for us to see"
			)
			replayURL = discord.ui.Label( 
				text="Evidence replay URL",
				component=discord.ui.TextInput(
					min_length=44,
					max_length=70,
					placeholder="https://warthunder.com/en/tournament/replay/...",
					required=True,
					custom_id="evidence",
					style=discord.TextStyle.short
				)
			)
			def __init__(self, bot:'Bot'):
				self.bot = bot
				self._logger = logging.getLogger(__name__)
				super().__init__()
			async def on_submit(self, interaction: discord.Interaction):
				await interaction.response.defer(thinking=True, ephemeral=True)
				REPLAY_URL_REGEX = re.compile(r"^https://warthunder\.com/[a-z]{2}/tournament/replay/\d+$")
				url:str = self.replayURL.component.value.strip().rstrip("/")
				url = re.sub(r"/[a-z]{2}/", "/en/", url)
				report_category:discord.ui.Select = self.find_item(69)
				if not REPLAY_URL_REGEX.match(url):
					await interaction.edit_original_response(content="**Invalid replay URL format.** Please use a valid War Thunder replay link: `https://warthunder.com/en/tournament/replay/<id>`")
					return
				username:str = self.user.component.value.strip()
				report_msg:str = self.message.component.value.strip()
				if self.bot.db.getByName(username) is None:
					await interaction.edit_original_response(content="No such user exists in our database")
					return
				isInReplay = await wtUtil.userInReplay(username, url.split("/")[-1], self.bot.gaijinLogin())
				if not isInReplay:
					await interaction.edit_original_response(content=f"The match you provided a link to does not have the user '{username}' in it.")
					return
				embed = discord.Embed(title=f"Report")
				embed.set_author(name=f"{interaction.user.name} (ID: {interaction.user.id})", icon_url=interaction.user.display_avatar.url)
				embed.add_field(name="WT username", value=username, inline=False)
				if report_msg:
					embed.add_field(name="Report message", value=report_msg, inline=False)
				embed.add_field(name="Category", value=report_category.values[0])
				embed.add_field(name="Evidence URL", value=url, inline=False)
				self._logger.debug(f"Report from {interaction.user.name} ({interaction.user.id})\nWT username: {username}\nReport message: {report_msg}")
				await self.bot.get_channel(self.bot.spamChID).send(embed=embed)
				await interaction.edit_original_response(content="Report submitted!")
			async def on_error(self, interaction: discord.Interaction, error: Exception) -> None:
				await interaction.edit_original_response(content='Oops! Something went wrong.')
				self._logger.exception("An error occured while reporting", exc_info=error)
		await interaction.response.send_modal(ReportModal(self.bot))

	@discord.app_commands.command()
	async def info(self, interaction:discord.Interaction):
		embed = discord.Embed(title="Bot info", color=0xFF0000)
		embed.add_field(name="Ping", value=f"{round(self.bot.latency*1000, 2)} ms", inline=False)
		uptime = datetime.now(UTC)-self.bot.runningSince
		embed.add_field(name="Uptime", value=f"{uptime.days}d {uptime.seconds//60//60}h {uptime.seconds//60%60}m {uptime.seconds%60}s", inline=False)
		memstat = virtual_memory()
		embed.add_field(name="Bot RAM usage", value=bytes2human(psutilProcess().memory_info().rss))
		embed.add_field(name="System RAM usage", value=f"{bytes2human(memstat.used)}/{bytes2human(memstat.total)}", inline=False)
		embed.set_author(name="PECK bot", icon_url=self.bot.iconURL)
		await interaction.response.send_message(embed=embed)

	@discord.app_commands.command()
	async def convert_to_gif(self, interaction:discord.Interaction, image:discord.Attachment):
		await interaction.response.defer(thinking=True)
		try:
			file = await genericUtil.convertImageToGif(image)
		except ValueError as e:
			await interaction.edit_original_response(content=e)
			return
		await interaction.edit_original_response(content="Here you go", attachments=[file,])
	# endregion
	# region Clip Commands
	group_clips = discord.app_commands.Group(name="clips", description="Commands for adding user clips", allowed_contexts=discord.app_commands.AppCommandContext(guild=True))

	@group_clips.command(name="upload")
	async def upload_clip(self, interaction:discord.Interaction):
		VIDEO_FORMATS = [".mp4", ".mov", ".webm"]
		class CreateClipCh(discord.ui.Modal, title="Upload Clip"):
			userCh = discord.ui.Label(
				text="User to upload for", 
				component=discord.ui.UserSelect(
					required=True,
					max_values=1,
					min_values=1,
					id=0
				)
			)
			_ = discord.ui.TextDisplay(content="Provide at least one of the items below")
			clips = discord.ui.Label(
				text="Clip",
				component=discord.ui.FileUpload(
					min_values=1,
					required=False,
					id=1
				)
			)
			medal_url = discord.ui.Label(
				text="Medal share link",
				component=discord.ui.TextInput(
					placeholder="https://medal.tv/games/war-thunder/clips/...",
					min_length=42,
					required=False,
					id=2
				)
			)
			def __init__(self, bot:'Bot'):
				super().__init__()
				self.bot = bot
				self._logger = logging.getLogger(__name__)
			async def on_submit(self, interaction:discord.Interaction):
				await interaction.response.defer(ephemeral=True, thinking=True)
				selected_user:discord.Member = self.find_item(0).values[0]
				clips:list[discord.Attachment] = self.find_item(1).values
				medal_url = self.find_item(2).value
				self._logger.debug(f"clips={clips}, selected_user={selected_user}, medal_url={medal_url}")	
				if not clips and not medal_url:
					await interaction.edit_original_response(content=f"You didn't provide a clip nor a medal url.")
					return
				for clip in clips:
					if self.bot.debug: continue
					for ext in VIDEO_FORMATS: 
						if clip.filename.endswith(ext): break
					else:
						await interaction.edit_original_response(content=f"One of the attachments were not an accepted video format. The following formats are accepted: {", ".join(VIDEO_FORMATS)}")
						return
				CLIPS_CATEGORY:discord.CategoryChannel|None = next((c for c in self.bot.get_guild(self.bot.peckServer).categories if c.id == self.bot.clipsCategory), None)
				if CLIPS_CATEGORY is None: raise LookupError("Could not find the Clips category")
				subjects = CLIPS_CATEGORY.text_channels
				self._logger.debug(f"Subjects: {subjects}")
				self._logger.debug(f"Category: {CLIPS_CATEGORY}")
				async def accept(interaction:discord.Interaction):
					original_msg = await interaction.original_response()
					await original_msg.edit(content=original_msg.content.removesuffix("\nAwaiting confirmation..."))
					return True
				async def deny(interaction:discord.Interaction):
					await interaction.message.delete()
					if len([i async for i in interaction.message.channel.history(limit=1)]) == 0:
						await interaction.message.channel.delete("Clips channel became empty")
					return True
				if not any([int(i.name) == selected_user.id for i in subjects]):
					channel = await CLIPS_CATEGORY.create_text_channel(str(selected_user.id), reason="User clip channel request")
				else:
					channel = [i for i in subjects if int(i.name) == selected_user.id][0]
				for clip in clips:
					while self.bot.is_ws_ratelimited():
						await asyncio.sleep(0.5)
					await channel.send(f"Sent by: {interaction.user.name} ({interaction.user.id})\nAwaiting confirmation...", view=genericUtil.genericButtons(acceptFunc=accept, denyFunc=deny, removeButtonsAfter=True, acceptLabel="Accept", denyLabel="Delete", timeout=None), file=await clip.to_file())
				if medal_url:
					try:
						file = await genericUtil.medalDownload(medal_url)
					except LookupError as e:
						await interaction.edit_original_response(content=f"Something went wrong when trying to download the clip: {e}")
						return
					while self.bot.is_ws_ratelimited():
						await asyncio.sleep(0.5)
					try:
						await channel.send(f"Sent by: {interaction.user.name} ({interaction.user.id})\nAwaiting confirmation...", view=genericUtil.genericButtons(acceptFunc=accept, denyFunc=deny, removeButtonsAfter=True, acceptLabel="Accept", denyLabel="Delete", timeout=None), file=file)
					except discord.HTTPException as e:
						if e.status == 413:
							await interaction.edit_original_response(content=f"The medal file appears to be too big")
							return
						raise
				await interaction.edit_original_response(content=f"Uploaded clip for {selected_user}.")
		await interaction.response.send_modal(CreateClipCh(self.bot))

	@group_clips.command(name="add_alias")
	async def add_alias(self, interaction:discord.Interaction):
		class AddAlias(discord.ui.Modal, title="Add alias to user"):
			userCh = discord.ui.Label(
				text="User to upload for", 
				component=discord.ui.UserSelect(
					required=True,
					max_values=1,
					min_values=1,
					id=0
				)
			)
			userAlias = discord.ui.Label(
				text="Alias of user",
				component=discord.ui.TextInput(
					required=True,
					min_length=1,
					max_length=64,
					style=discord.TextStyle.short
				)
			)
			def __init__(self, bot:'Bot'):
				super().__init__()
				self.bot = bot
				self._logger = logging.getLogger(__name__)
			async def on_submit(self, interaction:discord.Interaction):
				await interaction.response.defer(ephemeral=True, thinking=True)
				selected_user:discord.Member = self.userCh.component.values[0]
				alias:str = self.userAlias.component.value
				CLIPS_CATEGORY:discord.CategoryChannel|None = next((c for c in self.bot.get_guild(self.bot.peckServer).categories if c.id == self.bot.clipsCategory), None)
				if CLIPS_CATEGORY is None:
					raise LookupError("Could not find the Clips category")
				subjects = CLIPS_CATEGORY.text_channels
				self._logger.debug(f"Subjects: {subjects}")
				self._logger.debug(f"Category: {CLIPS_CATEGORY}")
				if not any([int(i.name) == selected_user.id for i in subjects]):
					await interaction.edit_original_response(content="The selected user does not have a clip channel. Use the command `/upload_clip` to create one")
					return
				channel:discord.TextChannel = [i for i in subjects if int(i.name) == selected_user.id][0]
				async def accept(interaction:discord.Interaction):
					alias:str = interaction.message.content.split("\n")[1].strip('"').lower()
					await interaction.message.delete()
					await interaction.channel.edit(topic=(interaction.channel.topic+"\n" if interaction.channel.topic is not None else "")+f"{alias}")
				async def deny(interaction:discord.Interaction):
					await interaction.message.delete()
				await channel.send(content=f"User '{interaction.user.name}' ({interaction.user.id}) suggested the following alias for user:\n\"{alias}\"\nAwaiting confirmation...", view=genericUtil.genericButtons(acceptFunc=accept, denyFunc=deny, acceptLabel="Add", denyLabel="Delete", timeout=None))
				await interaction.edit_original_response(content=f"Uploaded alias for {selected_user}. Said alias will be accessible after admin confirmation.")
		await interaction.response.send_modal(AddAlias(self.bot))
	# endregion
	# region Download Commands
	group_download = discord.app_commands.Group(name="download", description="Commands for downloading media from providers")

	@group_download.command(name="medal", description="Gives the raw .mp4 of the shared link")
	@discord.app_commands.describe(share_url="Medal share link to the clip")
	async def download_medal_clip(self, interaction:discord.Interaction, share_url:str):
		URL_REGEX = re.compile(r"https://medal.tv/games/(.*)/clips/(.*)")
		if URL_REGEX.fullmatch(share_url) is None:
			await interaction.response.send_message(content="You did not provide a valid medal.tv url.", ephemeral=True)
			return
		await interaction.response.defer(thinking=True, ephemeral=True)  
		try:
			clip = await genericUtil.medalDownload(share_url)
		except LookupError as e:
			await interaction.edit_original_response(content=f"An error occurred while downloading the clip: {e}")
			return
		await interaction.followup.send(f"Here you go.", file=clip)
	# endregion
	# region Request Commands
	group_requests = discord.app_commands.Group(name="request", description="For requesting user data from our database")

	@group_requests.command(name="warthunder")
	@discord.app_commands.describe(user="The user to search for")
	async def request_user(self, interaction: discord.Interaction, user:discord.Member):
		"""Requests a user's War Thunder username(s) from the database"""
		await interaction.response.defer(thinking=True)
		if not (db_user := self.bot.db.getByDID(user.id)):
			await interaction.edit_original_response(content="User not found")
			return
		await interaction.edit_original_response(content=f"User(s) found: {", ".join(f"{i.username}" for i in db_user)}")

	@group_requests.command(name="discord")
	@discord.app_commands.describe(user="The War Thunder username to search for")
	async def request(self, interaction: discord.Interaction, user:str):
		"""Requests a user's ID based on the WT username in the database"""
		await interaction.response.defer(thinking=True)
		if (db_user := self.bot.db.getByName(user)) is not None:
			dcuser = self.bot.get_guild(self.bot.peckServer).get_member(db_user.discord_id)
			if dcuser is None:
				await interaction.edit_original_response(content=f"User can no longer be found in the server (User ID was {db_user.discord_id})")
			else:
				await interaction.edit_original_response(content=f"User found: {dcuser.name} ({dcuser.id})")
		else:
			await interaction.edit_original_response(content="User not found")
	# endregion

async def setup(bot:'Bot'):
	await bot.add_cog(NormalCog(bot))
